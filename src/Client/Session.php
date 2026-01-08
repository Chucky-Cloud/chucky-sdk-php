<?php

declare(strict_types=1);

namespace ChuckyCloud\Sdk\Client;

use ChuckyCloud\Sdk\Tools\McpClientToolsServer;
use ChuckyCloud\Sdk\Tools\McpServerDefinition;
use ChuckyCloud\Sdk\Tools\ToolResult;
use ChuckyCloud\Sdk\Transport\TransportEvents;
use ChuckyCloud\Sdk\Transport\WebSocketTransport;
use ChuckyCloud\Sdk\Types\AssistantMessage;
use ChuckyCloud\Sdk\Types\ControlAction;
use ChuckyCloud\Sdk\Types\ControlMessage;
use ChuckyCloud\Sdk\Types\ErrorMessage;
use ChuckyCloud\Sdk\Types\InitMessage;
use ChuckyCloud\Sdk\Types\Message;
use ChuckyCloud\Sdk\Types\Model;
use ChuckyCloud\Sdk\Types\ResultMessage;
use ChuckyCloud\Sdk\Types\SessionException;
use ChuckyCloud\Sdk\Types\SessionOptions;
use ChuckyCloud\Sdk\Types\SessionState;
use ChuckyCloud\Sdk\Types\SystemMessage;
use ChuckyCloud\Sdk\Types\SystemSubtype;
use ChuckyCloud\Sdk\Types\ToolCallMessage;
use ChuckyCloud\Sdk\Types\ToolResultMessage;
use ChuckyCloud\Sdk\Types\UserMessage;
use Ramsey\Uuid\Uuid;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;

use function ChuckyCloud\Sdk\Tools\errorResult;

/**
 * Session event handlers
 */
class SessionEventHandlers
{
    /** @var callable|null */
    public mixed $onMessage = null;

    /** @var callable|null */
    public mixed $onError = null;

    /** @var callable|null */
    public mixed $onClose = null;
}

/**
 * Session manages a multi-turn conversation with Claude
 */
class Session
{
    private WebSocketTransport $transport;
    private SessionOptions $options;
    private bool $debug;

    private string $sessionId;
    private SessionState $state = SessionState::IDLE;
    private bool $connected = false;
    private SessionEventHandlers $handlers;

    /** @var array<string, callable> */
    private array $toolHandlers = [];

    /** @var Message[] */
    private array $messageBuffer = [];

    /** @var Deferred|null */
    private ?Deferred $readyDeferred = null;

    /** @var Deferred|null */
    private ?Deferred $messageDeferred = null;

    public function __construct(
        WebSocketTransport $transport,
        SessionOptions $options,
        bool $debug = false,
    ) {
        $this->transport = $transport;
        $this->options = $options;
        $this->debug = $debug;
        $this->sessionId = '';
        $this->handlers = new SessionEventHandlers();

        // Extract tool handlers from MCP servers
        $this->extractToolHandlers();

        // Set up transport event handlers
        $events = new TransportEvents();
        $events->onMessage = fn(Message $msg) => $this->handleMessage($msg);
        $events->onClose = fn($code, $reason) => $this->handleClose($code, $reason);
        $events->onError = fn(\Exception $e) => $this->handleError($e);
        $this->transport->setEventHandlers($events);
    }

    /**
     * Get the session ID
     */
    public function getId(): string
    {
        return $this->sessionId;
    }

    /**
     * Get the current state
     */
    public function getState(): SessionState
    {
        return $this->state;
    }

    /**
     * Set event handlers
     */
    public function on(SessionEventHandlers $handlers): self
    {
        $this->handlers = $handlers;
        return $this;
    }

    /**
     * Connect and initialize the session
     */
    public function connect(): PromiseInterface
    {
        if ($this->connected) {
            $deferred = new Deferred();
            $deferred->resolve(true);
            return $deferred->promise();
        }

        $this->state = SessionState::INITIALIZING;
        $this->readyDeferred = new Deferred();

        $this->transport->connect()->then(
            function () {
                // Send init message
                $this->sendInit();
            },
            function (\Exception $e) {
                $this->state = SessionState::ERROR;
                if ($this->readyDeferred !== null) {
                    $this->readyDeferred->reject($e);
                }
            }
        );

        return $this->readyDeferred->promise();
    }

    private function sendInit(): void
    {
        $payload = $this->options->toArray();

        // Serialize MCP servers
        if ($this->options->mcpServers !== null) {
            $servers = [];
            foreach ($this->options->mcpServers as $server) {
                if ($server instanceof McpServerDefinition) {
                    $servers[] = $server->toArray();
                } elseif (is_array($server)) {
                    $servers[] = $server;
                }
            }
            $payload['mcpServers'] = $servers;
        }

        $this->transport->send(new InitMessage($payload));
    }

    /**
     * Send a message to Claude
     */
    public function send(string $message): PromiseInterface
    {
        $deferred = new Deferred();

        // Auto-connect if needed
        if (!$this->connected) {
            $this->connect()->then(
                fn() => $this->doSend($message, $deferred),
                fn(\Exception $e) => $deferred->reject($e)
            );
        } else {
            $this->doSend($message, $deferred);
        }

        return $deferred->promise();
    }

    private function doSend(string $message, Deferred $deferred): void
    {
        $this->state = SessionState::PROCESSING;

        $sessionId = $this->sessionId ?: 'unknown';

        $userMessage = new UserMessage(
            sessionId: $sessionId,
            content: $message,
            uuid: Uuid::uuid4()->toString(),
        );

        $this->transport->send($userMessage);
        $deferred->resolve(true);
    }

    /**
     * Wait for the next message
     */
    public function receive(): PromiseInterface
    {
        // Check buffer first
        if (!empty($this->messageBuffer)) {
            $msg = array_shift($this->messageBuffer);
            $deferred = new Deferred();
            $deferred->resolve($msg);
            return $deferred->promise();
        }

        // Wait for new message
        $this->messageDeferred = new Deferred();
        return $this->messageDeferred->promise();
    }

    /**
     * Close the session
     */
    public function close(): void
    {
        $this->transport->send(new ControlMessage(ControlAction::CLOSE));
        $this->transport->disconnect();
        $this->state = SessionState::COMPLETED;

        if ($this->handlers->onClose !== null) {
            ($this->handlers->onClose)();
        }
    }

    private function handleMessage(Message $msg): void
    {
        // Handle ready signals during initialization
        if (!$this->connected) {
            if ($msg instanceof ControlMessage) {
                if ($msg->action === ControlAction::READY || $msg->action === ControlAction::SESSION_INFO) {
                    $this->connected = true;
                    $this->state = SessionState::READY;
                    if ($this->readyDeferred !== null) {
                        $this->readyDeferred->resolve(true);
                    }
                    return;
                }
            }

            if ($msg instanceof SystemMessage && $msg->subtype === SystemSubtype::INIT) {
                // Extract session ID from system init
                $data = $msg->data;
                if (is_array($data) && isset($data['session_id'])) {
                    $this->sessionId = $data['session_id'];
                } elseif ($msg->sessionId) {
                    $this->sessionId = $msg->sessionId;
                }

                $this->connected = true;
                $this->state = SessionState::READY;
                if ($this->readyDeferred !== null) {
                    $this->readyDeferred->resolve(true);
                }
            }

            if ($msg instanceof ErrorMessage) {
                $this->state = SessionState::ERROR;
                if ($this->readyDeferred !== null) {
                    $this->readyDeferred->reject(new SessionException($msg->message));
                }
            }
        }

        // Handle tool calls
        if ($msg instanceof ToolCallMessage) {
            $this->handleToolCall($msg);
            return;
        }

        // Update state on result
        if ($msg instanceof ResultMessage) {
            $this->state = SessionState::READY;
        }

        // Forward to waiting receiver or buffer
        if ($this->messageDeferred !== null) {
            $deferred = $this->messageDeferred;
            $this->messageDeferred = null;
            $deferred->resolve($msg);
        } else {
            $this->messageBuffer[] = $msg;
        }

        // Call handler
        if ($this->handlers->onMessage !== null) {
            ($this->handlers->onMessage)($msg);
        }
    }

    private function handleToolCall(ToolCallMessage $call): void
    {
        $this->state = SessionState::WAITING_TOOL;

        $handler = $this->toolHandlers[$call->toolName] ?? null;

        if ($handler === null) {
            $result = errorResult("Tool not found: {$call->toolName}");
        } else {
            try {
                $result = $handler($call->input);
                if (!$result instanceof ToolResult) {
                    $result = errorResult('Tool handler did not return a ToolResult');
                }
            } catch (\Exception $e) {
                $result = errorResult("Tool execution error: {$e->getMessage()}");
            }
        }

        $this->transport->send(new ToolResultMessage($call->callId, $result->toArray()));
        $this->state = SessionState::PROCESSING;
    }

    private function handleClose(?int $code, ?string $reason): void
    {
        $this->state = SessionState::COMPLETED;
    }

    private function handleError(\Exception $e): void
    {
        if ($this->handlers->onError !== null) {
            ($this->handlers->onError)($e);
        }
    }

    private function extractToolHandlers(): void
    {
        if ($this->options->mcpServers === null) {
            return;
        }

        foreach ($this->options->mcpServers as $server) {
            if ($server instanceof McpClientToolsServer) {
                foreach ($server->getHandlers() as $name => $handler) {
                    $this->toolHandlers[$name] = $handler;
                }
            }
        }
    }
}

/**
 * Get assistant text from a message
 */
function getAssistantText(Message $msg): ?string
{
    if ($msg instanceof AssistantMessage) {
        return $msg->getText();
    }
    return null;
}

/**
 * Get result text from a message
 */
function getResultText(Message $msg): ?string
{
    if ($msg instanceof ResultMessage) {
        return $msg->result;
    }
    return null;
}
