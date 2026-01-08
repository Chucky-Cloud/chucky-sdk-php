<?php

declare(strict_types=1);

namespace ChuckyCloud\Sdk\Types;

/**
 * Base exception for all Chucky SDK errors
 */
class ChuckyException extends \Exception
{
    public readonly ?string $errorCode;
    public readonly mixed $details;

    public function __construct(
        string $message,
        ?string $errorCode = null,
        mixed $details = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
        $this->errorCode = $errorCode;
        $this->details = $details;
    }
}

/**
 * Connection error
 */
class ConnectionException extends ChuckyException
{
    public function __construct(string $message, ?\Throwable $previous = null)
    {
        parent::__construct($message, errorCode: 'CONNECTION_ERROR', previous: $previous);
    }
}

/**
 * Authentication error
 */
class AuthenticationException extends ChuckyException
{
    public function __construct(string $message, ?\Throwable $previous = null)
    {
        parent::__construct($message, errorCode: 'AUTHENTICATION_ERROR', previous: $previous);
    }
}

/**
 * Budget exceeded error
 */
class BudgetExceededException extends ChuckyException
{
    public function __construct(string $message = 'Budget exceeded', ?\Throwable $previous = null)
    {
        parent::__construct($message, errorCode: 'BUDGET_EXCEEDED', previous: $previous);
    }
}

/**
 * Concurrency limit error
 */
class ConcurrencyLimitException extends ChuckyException
{
    public function __construct(string $message = 'Concurrency limit reached', ?\Throwable $previous = null)
    {
        parent::__construct($message, errorCode: 'CONCURRENCY_LIMIT', previous: $previous);
    }
}

/**
 * Rate limit error
 */
class RateLimitException extends ChuckyException
{
    public function __construct(string $message = 'Rate limit exceeded', ?\Throwable $previous = null)
    {
        parent::__construct($message, errorCode: 'RATE_LIMIT', previous: $previous);
    }
}

/**
 * Session error
 */
class SessionException extends ChuckyException
{
    public function __construct(string $message, ?\Throwable $previous = null)
    {
        parent::__construct($message, errorCode: 'SESSION_ERROR', previous: $previous);
    }
}

/**
 * Tool execution error
 */
class ToolExecutionException extends ChuckyException
{
    public function __construct(string $message, ?\Throwable $previous = null)
    {
        parent::__construct($message, errorCode: 'TOOL_EXECUTION_ERROR', previous: $previous);
    }
}

/**
 * Timeout error
 */
class TimeoutException extends ChuckyException
{
    public function __construct(string $message = 'Operation timed out', ?\Throwable $previous = null)
    {
        parent::__construct($message, errorCode: 'TIMEOUT', previous: $previous);
    }
}

/**
 * Validation error
 */
class ValidationException extends ChuckyException
{
    public function __construct(string $message, ?\Throwable $previous = null)
    {
        parent::__construct($message, errorCode: 'VALIDATION_ERROR', previous: $previous);
    }
}

/**
 * Protocol error
 */
class ProtocolException extends ChuckyException
{
    public function __construct(string $message, ?\Throwable $previous = null)
    {
        parent::__construct($message, errorCode: 'PROTOCOL_ERROR', previous: $previous);
    }
}
