<?php

declare(strict_types=1);

namespace ChuckyCloud\Sdk\Transport;

use ChuckyCloud\Sdk\Types\ConnectionException;
use ChuckyCloud\Sdk\Types\Message;
use ChuckyCloud\Sdk\Types\ProtocolException;
use Ratchet\Client\Connector;
use Ratchet\Client\WebSocket;
use Ratchet\RFC6455\Messaging\MessageInterface;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;

use function ChuckyCloud\Sdk\Types\parseMessage;

/**
 * Connection status
 */
enum ConnectionStatus: string
{
    case DISCONNECTED = 'disconnected';
    case CONNECTING = 'connecting';
    case CONNECTED = 'connected';
    case ERROR = 'error';
}

/**
 * Transport event handlers
 */
class TransportEvents
{
    /** @var callable|null */
    public mixed $onMessage = null;

    /** @var callable|null */
    public mixed $onClose = null;

    /** @var callable|null */
    public mixed $onError = null;

    /** @var callable|null */
    public mixed $onStatusChange = null;
}

/**
 * WebSocket transport for communication with Chucky server
 */
class WebSocketTransport
{
    private string $baseUrl;
    private string $token;
    private float $timeout;
    private float $keepAliveInterval;
    private bool $debug;

    private ?WebSocket $connection = null;
    private ConnectionStatus $status = ConnectionStatus::DISCONNECTED;
    private TransportEvents $events;
    private LoopInterface $loop;

    /** @var Message[] */
    private array $messageQueue = [];

    /** @var Deferred|null */
    private ?Deferred $readyDeferred = null;

    public function __construct(
        string $baseUrl,
        string $token,
        float $timeout = 60.0,
        float $keepAliveInterval = 300.0,
        bool $debug = false,
        ?LoopInterface $loop = null,
    ) {
        $this->baseUrl = $baseUrl;
        $this->token = $token;
        $this->timeout = $timeout;
        $this->keepAliveInterval = $keepAliveInterval;
        $this->debug = $debug;
        $this->events = new TransportEvents();
        $this->loop = $loop ?? Loop::get();
    }

    /**
     * Set event handlers
     */
    public function setEventHandlers(TransportEvents $events): void
    {
        $this->events = $events;
    }

    /**
     * Get current status
     */
    public function getStatus(): ConnectionStatus
    {
        return $this->status;
    }

    private function setStatus(ConnectionStatus $status): void
    {
        $oldStatus = $this->status;
        $this->status = $status;

        if ($oldStatus !== $status && $this->events->onStatusChange !== null) {
            ($this->events->onStatusChange)($status);
        }
    }

    /**
     * Connect to the WebSocket server
     */
    public function connect(): PromiseInterface
    {
        $this->setStatus(ConnectionStatus::CONNECTING);

        $url = $this->baseUrl . '?token=' . urlencode($this->token);

        if ($this->debug) {
            echo "[WebSocket] Connecting to {$url}\n";
        }

        $this->readyDeferred = new Deferred();
        $connector = new Connector($this->loop);

        $connector($url)->then(
            function (WebSocket $conn) {
                $this->connection = $conn;
                $this->setStatus(ConnectionStatus::CONNECTED);

                if ($this->debug) {
                    echo "[WebSocket] Connected\n";
                }

                // Set up message handler
                $conn->on('message', function (MessageInterface $msg) {
                    $this->handleMessage($msg->getPayload());
                });

                // Set up close handler
                $conn->on('close', function ($code = null, $reason = null) {
                    if ($this->debug) {
                        echo "[WebSocket] Closed: {$code} {$reason}\n";
                    }
                    $this->setStatus(ConnectionStatus::DISCONNECTED);
                    if ($this->events->onClose !== null) {
                        ($this->events->onClose)($code, $reason);
                    }
                });

                // Flush queued messages
                $this->flushQueue();

                // Resolve ready promise
                if ($this->readyDeferred !== null) {
                    $this->readyDeferred->resolve(true);
                }
            },
            function (\Exception $e) {
                $this->setStatus(ConnectionStatus::ERROR);
                if ($this->debug) {
                    echo "[WebSocket] Connection failed: {$e->getMessage()}\n";
                }
                if ($this->events->onError !== null) {
                    ($this->events->onError)(new ConnectionException($e->getMessage(), $e));
                }
                if ($this->readyDeferred !== null) {
                    $this->readyDeferred->reject($e);
                }
            }
        );

        return $this->readyDeferred->promise();
    }

    /**
     * Disconnect from the WebSocket server
     */
    public function disconnect(): void
    {
        if ($this->connection !== null) {
            $this->connection->close();
            $this->connection = null;
        }
        $this->setStatus(ConnectionStatus::DISCONNECTED);
    }

    /**
     * Send a message
     */
    public function send(Message $message): void
    {
        if ($this->connection === null || $this->status !== ConnectionStatus::CONNECTED) {
            $this->messageQueue[] = $message;
            return;
        }

        $this->sendImmediate($message);
    }

    private function sendImmediate(Message $message): void
    {
        if ($this->connection === null) {
            throw new ConnectionException('Not connected');
        }

        $json = json_encode($message->toArray());

        if ($this->debug) {
            echo "[WebSocket] Sending: {$json}\n";
        }

        $this->connection->send($json);
    }

    private function flushQueue(): void
    {
        $queue = $this->messageQueue;
        $this->messageQueue = [];

        foreach ($queue as $message) {
            try {
                $this->sendImmediate($message);
            } catch (\Exception $e) {
                if ($this->events->onError !== null) {
                    ($this->events->onError)($e);
                }
            }
        }
    }

    private function handleMessage(string $payload): void
    {
        if ($this->debug) {
            echo "[WebSocket] Received: {$payload}\n";
        }

        try {
            $data = json_decode($payload, true);
            if ($data === null) {
                throw new ProtocolException('Invalid JSON');
            }

            $message = parseMessage($data);

            if ($this->events->onMessage !== null) {
                ($this->events->onMessage)($message);
            }
        } catch (\Exception $e) {
            if ($this->events->onError !== null) {
                ($this->events->onError)($e);
            }
        }
    }

    /**
     * Get the event loop
     */
    public function getLoop(): LoopInterface
    {
        return $this->loop;
    }

    /**
     * Run the event loop
     */
    public function run(): void
    {
        $this->loop->run();
    }

    /**
     * Stop the event loop
     */
    public function stop(): void
    {
        $this->loop->stop();
    }
}
