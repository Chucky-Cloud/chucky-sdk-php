<?php

declare(strict_types=1);

namespace ChuckyCloud\Sdk\Types;

/**
 * Client configuration options
 */
class ClientOptions
{
    public function __construct(
        public readonly string $token,
        public readonly string $baseUrl = 'wss://conjure.chucky.cloud/ws',
        public readonly bool $debug = false,
        public readonly float $timeout = 60.0,
        public readonly float $keepAliveInterval = 300.0,
        public readonly bool $autoReconnect = false,
        public readonly int $maxReconnectAttempts = 3,
    ) {}
}

/**
 * Output format configuration
 */
class OutputFormat
{
    public function __construct(
        public readonly string $type,
        public readonly mixed $schema,
    ) {}

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'schema' => $this->schema,
        ];
    }
}

/**
 * Base options for sessions
 */
class BaseOptions
{
    /**
     * @param Model|string|null $model
     * @param string|null $fallbackModel
     * @param string|array|null $systemPrompt
     * @param int|null $maxTurns
     * @param float|null $maxBudgetUsd
     * @param int|null $maxThinkingTokens
     * @param array|null $tools
     * @param array|null $mcpServers
     * @param PermissionMode|null $permissionMode
     * @param OutputFormat|null $outputFormat
     * @param bool $includePartialMessages
     * @param array|null $env
     */
    public function __construct(
        public readonly Model|string|null $model = null,
        public readonly ?string $fallbackModel = null,
        public readonly string|array|null $systemPrompt = null,
        public readonly ?int $maxTurns = null,
        public readonly ?float $maxBudgetUsd = null,
        public readonly ?int $maxThinkingTokens = null,
        public readonly ?array $tools = null,
        public readonly ?array $mcpServers = null,
        public readonly ?PermissionMode $permissionMode = null,
        public readonly ?OutputFormat $outputFormat = null,
        public readonly bool $includePartialMessages = false,
        public readonly ?array $env = null,
    ) {}

    public function toArray(): array
    {
        $data = [];

        if ($this->model !== null) {
            $data['model'] = $this->model instanceof Model ? $this->model->value : $this->model;
        }
        if ($this->fallbackModel !== null) {
            $data['fallbackModel'] = $this->fallbackModel;
        }
        if ($this->systemPrompt !== null) {
            $data['systemPrompt'] = $this->systemPrompt;
        }
        if ($this->maxTurns !== null) {
            $data['maxTurns'] = $this->maxTurns;
        }
        if ($this->maxBudgetUsd !== null) {
            $data['maxBudgetUsd'] = $this->maxBudgetUsd;
        }
        if ($this->maxThinkingTokens !== null) {
            $data['maxThinkingTokens'] = $this->maxThinkingTokens;
        }
        if ($this->tools !== null) {
            $data['tools'] = $this->tools;
        }
        if ($this->mcpServers !== null) {
            $data['mcpServers'] = $this->mcpServers;
        }
        if ($this->permissionMode !== null) {
            $data['permissionMode'] = $this->permissionMode->value;
        }
        if ($this->outputFormat !== null) {
            $data['outputFormat'] = $this->outputFormat->toArray();
        }
        if ($this->includePartialMessages) {
            $data['includePartialMessages'] = true;
        }
        if ($this->env !== null) {
            $data['env'] = $this->env;
        }

        return $data;
    }
}

/**
 * Session configuration options
 */
class SessionOptions extends BaseOptions
{
    public function __construct(
        Model|string|null $model = null,
        ?string $fallbackModel = null,
        string|array|null $systemPrompt = null,
        ?int $maxTurns = null,
        ?float $maxBudgetUsd = null,
        ?int $maxThinkingTokens = null,
        ?array $tools = null,
        ?array $mcpServers = null,
        ?PermissionMode $permissionMode = null,
        ?OutputFormat $outputFormat = null,
        bool $includePartialMessages = false,
        ?array $env = null,
        public readonly ?string $sessionId = null,
        public readonly bool $forkSession = false,
        public readonly ?string $resumeSessionAt = null,
        public readonly bool $continue = false,
    ) {
        parent::__construct(
            $model,
            $fallbackModel,
            $systemPrompt,
            $maxTurns,
            $maxBudgetUsd,
            $maxThinkingTokens,
            $tools,
            $mcpServers,
            $permissionMode,
            $outputFormat,
            $includePartialMessages,
            $env,
        );
    }

    public function toArray(): array
    {
        $data = parent::toArray();

        if ($this->forkSession) {
            $data['forkSession'] = true;
        }
        if ($this->resumeSessionAt !== null) {
            $data['resumeSessionAt'] = $this->resumeSessionAt;
        }
        if ($this->continue) {
            $data['continue'] = true;
        }

        return $data;
    }
}
