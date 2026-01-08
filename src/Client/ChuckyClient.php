<?php

declare(strict_types=1);

namespace ChuckyCloud\Sdk\Client;

use ChuckyCloud\Sdk\Transport\WebSocketTransport;
use ChuckyCloud\Sdk\Types\ClientOptions;
use ChuckyCloud\Sdk\Types\SessionOptions;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;

/**
 * Client event handlers
 */
class ClientEventHandlers
{
    /** @var callable|null */
    public mixed $onConnect = null;

    /** @var callable|null */
    public mixed $onDisconnect = null;

    /** @var callable|null */
    public mixed $onSessionStart = null;

    /** @var callable|null */
    public mixed $onSessionEnd = null;

    /** @var callable|null */
    public mixed $onError = null;
}

/**
 * Main client for interacting with Chucky
 */
class ChuckyClient
{
    private ClientOptions $options;
    private LoopInterface $loop;
    private ClientEventHandlers $handlers;

    /** @var Session[] */
    private array $sessions = [];

    public function __construct(ClientOptions $options, ?LoopInterface $loop = null)
    {
        $this->options = $options;
        $this->loop = $loop ?? Loop::get();
        $this->handlers = new ClientEventHandlers();
    }

    /**
     * Set event handlers
     */
    public function on(ClientEventHandlers $handlers): self
    {
        $this->handlers = $handlers;
        return $this;
    }

    /**
     * Create a new session
     */
    public function createSession(?SessionOptions $options = null): Session
    {
        $options ??= new SessionOptions();

        $transport = new WebSocketTransport(
            baseUrl: $this->options->baseUrl,
            token: $this->options->token,
            timeout: $this->options->timeout,
            keepAliveInterval: $this->options->keepAliveInterval,
            debug: $this->options->debug,
            loop: $this->loop,
        );

        $session = new Session($transport, $options, $this->options->debug);
        $this->sessions[] = $session;

        if ($this->handlers->onSessionStart !== null) {
            ($this->handlers->onSessionStart)($session->getId());
        }

        return $session;
    }

    /**
     * Resume an existing session
     */
    public function resumeSession(string $sessionId, ?SessionOptions $options = null): Session
    {
        $options = new SessionOptions(
            model: $options?->model,
            fallbackModel: $options?->fallbackModel,
            systemPrompt: $options?->systemPrompt,
            maxTurns: $options?->maxTurns,
            maxBudgetUsd: $options?->maxBudgetUsd,
            maxThinkingTokens: $options?->maxThinkingTokens,
            tools: $options?->tools,
            mcpServers: $options?->mcpServers,
            permissionMode: $options?->permissionMode,
            outputFormat: $options?->outputFormat,
            includePartialMessages: $options?->includePartialMessages ?? false,
            env: $options?->env,
            sessionId: $sessionId,
            forkSession: $options?->forkSession ?? false,
            resumeSessionAt: $options?->resumeSessionAt,
            continue: true,
        );

        return $this->createSession($options);
    }

    /**
     * Close all sessions and the client
     */
    public function close(): void
    {
        foreach ($this->sessions as $session) {
            $session->close();
        }
        $this->sessions = [];
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

/**
 * Create a new client (factory function)
 */
function createClient(ClientOptions $options, ?LoopInterface $loop = null): ChuckyClient
{
    return new ChuckyClient($options, $loop);
}
