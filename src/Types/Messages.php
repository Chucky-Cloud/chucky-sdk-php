<?php

declare(strict_types=1);

namespace ChuckyCloud\Sdk\Types;

/**
 * Base interface for all messages
 */
interface Message
{
    public function getType(): MessageType;
    public function toArray(): array;
}

/**
 * Content block in a message
 */
class ContentBlock
{
    public function __construct(
        public readonly string $type,
        public readonly ?string $text = null,
        public readonly ?string $id = null,
        public readonly ?string $name = null,
        public readonly mixed $input = null,
        public readonly ?string $toolUseId = null,
        public readonly mixed $content = null,
        public readonly bool $isError = false,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            type: $data['type'],
            text: $data['text'] ?? null,
            id: $data['id'] ?? null,
            name: $data['name'] ?? null,
            input: $data['input'] ?? null,
            toolUseId: $data['tool_use_id'] ?? null,
            content: $data['content'] ?? null,
            isError: $data['is_error'] ?? false,
        );
    }
}

/**
 * Usage statistics
 */
class Usage
{
    public function __construct(
        public readonly int $inputTokens = 0,
        public readonly int $outputTokens = 0,
        public readonly int $cacheCreationInputTokens = 0,
        public readonly int $cacheReadInputTokens = 0,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            inputTokens: $data['input_tokens'] ?? 0,
            outputTokens: $data['output_tokens'] ?? 0,
            cacheCreationInputTokens: $data['cache_creation_input_tokens'] ?? 0,
            cacheReadInputTokens: $data['cache_read_input_tokens'] ?? 0,
        );
    }
}

/**
 * Init message sent to start a session
 */
class InitMessage implements Message
{
    public function __construct(
        public readonly array $payload,
    ) {}

    public function getType(): MessageType
    {
        return MessageType::INIT;
    }

    public function toArray(): array
    {
        return [
            'type' => 'init',
            'payload' => $this->payload,
        ];
    }
}

/**
 * User message
 */
class UserMessage implements Message
{
    public function __construct(
        public readonly string $sessionId,
        public readonly string $content,
        public readonly ?string $uuid = null,
        public readonly ?string $parentToolUseId = null,
    ) {}

    public function getType(): MessageType
    {
        return MessageType::USER;
    }

    public function toArray(): array
    {
        return [
            'type' => 'user',
            'uuid' => $this->uuid,
            'session_id' => $this->sessionId,
            'message' => [
                'role' => 'user',
                'content' => $this->content,
            ],
            'parent_tool_use_id' => $this->parentToolUseId,
        ];
    }
}

/**
 * Assistant message from Claude
 */
class AssistantMessage implements Message
{
    public function __construct(
        public readonly string $uuid,
        public readonly string $sessionId,
        public readonly array $content,
        public readonly ?string $parentToolUseId = null,
    ) {}

    public function getType(): MessageType
    {
        return MessageType::ASSISTANT;
    }

    public function toArray(): array
    {
        return [
            'type' => 'assistant',
            'uuid' => $this->uuid,
            'session_id' => $this->sessionId,
            'message' => [
                'role' => 'assistant',
                'content' => $this->content,
            ],
            'parent_tool_use_id' => $this->parentToolUseId,
        ];
    }

    /**
     * Extract text content from the message
     */
    public function getText(): string
    {
        $text = '';
        foreach ($this->content as $block) {
            if (is_array($block) && ($block['type'] ?? '') === 'text') {
                $text .= $block['text'] ?? '';
            }
        }
        return $text;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            uuid: $data['uuid'],
            sessionId: $data['session_id'],
            content: $data['message']['content'] ?? [],
            parentToolUseId: $data['parent_tool_use_id'] ?? null,
        );
    }
}

/**
 * Result message
 */
class ResultMessage implements Message
{
    public function __construct(
        public readonly ResultSubtype $subtype,
        public readonly string $uuid,
        public readonly string $sessionId,
        public readonly int $durationMs,
        public readonly int $durationApiMs,
        public readonly bool $isError,
        public readonly int $numTurns,
        public readonly float $totalCostUsd,
        public readonly Usage $usage,
        public readonly ?string $result = null,
        public readonly array $errors = [],
    ) {}

    public function getType(): MessageType
    {
        return MessageType::RESULT;
    }

    public function toArray(): array
    {
        return [
            'type' => 'result',
            'subtype' => $this->subtype->value,
            'uuid' => $this->uuid,
            'session_id' => $this->sessionId,
            'duration_ms' => $this->durationMs,
            'duration_api_ms' => $this->durationApiMs,
            'is_error' => $this->isError,
            'num_turns' => $this->numTurns,
            'result' => $this->result,
            'total_cost_usd' => $this->totalCostUsd,
            'usage' => [
                'input_tokens' => $this->usage->inputTokens,
                'output_tokens' => $this->usage->outputTokens,
            ],
            'errors' => $this->errors,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            subtype: ResultSubtype::from($data['subtype']),
            uuid: $data['uuid'],
            sessionId: $data['session_id'],
            durationMs: $data['duration_ms'] ?? 0,
            durationApiMs: $data['duration_api_ms'] ?? 0,
            isError: $data['is_error'] ?? false,
            numTurns: $data['num_turns'] ?? 0,
            totalCostUsd: $data['total_cost_usd'] ?? 0.0,
            usage: Usage::fromArray($data['usage'] ?? []),
            result: $data['result'] ?? null,
            errors: $data['errors'] ?? [],
        );
    }
}

/**
 * System message
 */
class SystemMessage implements Message
{
    public function __construct(
        public readonly SystemSubtype $subtype,
        public readonly string $uuid,
        public readonly string $sessionId,
        public readonly mixed $data = null,
    ) {}

    public function getType(): MessageType
    {
        return MessageType::SYSTEM;
    }

    public function toArray(): array
    {
        return [
            'type' => 'system',
            'subtype' => $this->subtype->value,
            'uuid' => $this->uuid,
            'session_id' => $this->sessionId,
            'data' => $this->data,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            subtype: SystemSubtype::from($data['subtype']),
            uuid: $data['uuid'] ?? '',
            sessionId: $data['session_id'] ?? '',
            data: $data['data'] ?? $data,
        );
    }
}

/**
 * Control message
 */
class ControlMessage implements Message
{
    public function __construct(
        public readonly ControlAction $action,
        public readonly mixed $data = null,
    ) {}

    public function getType(): MessageType
    {
        return MessageType::CONTROL;
    }

    public function toArray(): array
    {
        return [
            'type' => 'control',
            'payload' => [
                'action' => $this->action->value,
                'data' => $this->data,
            ],
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            action: ControlAction::from($data['payload']['action']),
            data: $data['payload']['data'] ?? null,
        );
    }
}

/**
 * Error message
 */
class ErrorMessage implements Message
{
    public function __construct(
        public readonly string $message,
        public readonly ?string $code = null,
        public readonly mixed $details = null,
    ) {}

    public function getType(): MessageType
    {
        return MessageType::ERROR;
    }

    public function toArray(): array
    {
        return [
            'type' => 'error',
            'payload' => [
                'message' => $this->message,
                'code' => $this->code,
                'details' => $this->details,
            ],
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            message: $data['payload']['message'] ?? 'Unknown error',
            code: $data['payload']['code'] ?? null,
            details: $data['payload']['details'] ?? null,
        );
    }
}

/**
 * Tool call message
 */
class ToolCallMessage implements Message
{
    public function __construct(
        public readonly string $callId,
        public readonly string $toolName,
        public readonly mixed $input,
    ) {}

    public function getType(): MessageType
    {
        return MessageType::TOOL_CALL;
    }

    public function toArray(): array
    {
        return [
            'type' => 'tool_call',
            'payload' => [
                'callId' => $this->callId,
                'toolName' => $this->toolName,
                'input' => $this->input,
            ],
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            callId: $data['payload']['callId'],
            toolName: $data['payload']['toolName'],
            input: $data['payload']['input'],
        );
    }
}

/**
 * Tool result message
 */
class ToolResultMessage implements Message
{
    public function __construct(
        public readonly string $callId,
        public readonly array $result,
    ) {}

    public function getType(): MessageType
    {
        return MessageType::TOOL_RESULT;
    }

    public function toArray(): array
    {
        return [
            'type' => 'tool_result',
            'payload' => [
                'callId' => $this->callId,
                'result' => $this->result,
            ],
        ];
    }
}

/**
 * Ping message
 */
class PingMessage implements Message
{
    public function __construct(
        public readonly int $timestamp,
    ) {}

    public function getType(): MessageType
    {
        return MessageType::PING;
    }

    public function toArray(): array
    {
        return [
            'type' => 'ping',
            'payload' => [
                'timestamp' => $this->timestamp,
            ],
        ];
    }
}

/**
 * Parse incoming message from JSON
 */
function parseMessage(array $data): Message
{
    $type = MessageType::tryFrom($data['type'] ?? '');

    return match ($type) {
        MessageType::ASSISTANT => AssistantMessage::fromArray($data),
        MessageType::RESULT => ResultMessage::fromArray($data),
        MessageType::SYSTEM => SystemMessage::fromArray($data),
        MessageType::CONTROL => ControlMessage::fromArray($data),
        MessageType::ERROR => ErrorMessage::fromArray($data),
        MessageType::TOOL_CALL => ToolCallMessage::fromArray($data),
        default => throw new \InvalidArgumentException("Unknown message type: {$data['type']}"),
    };
}
