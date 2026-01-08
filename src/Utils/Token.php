<?php

declare(strict_types=1);

namespace ChuckyCloud\Sdk\Utils;

use ChuckyCloud\Sdk\Types\BudgetWindow;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Token budget configuration
 */
class TokenBudget
{
    public function __construct(
        public readonly int $ai,
        public readonly int $compute,
        public readonly BudgetWindow $window,
        public readonly string $windowStart,
    ) {}

    public function toArray(): array
    {
        return [
            'ai' => $this->ai,
            'compute' => $this->compute,
            'window' => $this->window->value,
            'windowStart' => $this->windowStart,
        ];
    }
}

/**
 * Token permissions
 */
class TokenPermissions
{
    /**
     * @param string[]|null $allowedTools
     * @param int|null $maxTurns
     */
    public function __construct(
        public readonly ?array $allowedTools = null,
        public readonly ?int $maxTurns = null,
    ) {}

    public function toArray(): array
    {
        $data = [];
        if ($this->allowedTools !== null) {
            $data['allowedTools'] = $this->allowedTools;
        }
        if ($this->maxTurns !== null) {
            $data['maxTurns'] = $this->maxTurns;
        }
        return $data;
    }
}

/**
 * Options for creating a budget
 */
class CreateBudgetOptions
{
    public function __construct(
        public readonly float $aiDollars = 0.0,
        public readonly float $computeHours = 0.0,
        public readonly BudgetWindow $window = BudgetWindow::HOUR,
        public readonly ?\DateTimeInterface $windowStart = null,
    ) {}
}

/**
 * Options for creating a token
 */
class CreateTokenOptions
{
    public function __construct(
        public readonly string $userId,
        public readonly string $projectId,
        public readonly string $secret,
        public readonly ?TokenBudget $budget = null,
        public readonly int $expiresIn = 3600,
        public readonly ?TokenPermissions $permissions = null,
        public readonly mixed $sdkConfig = null,
    ) {}
}

/**
 * Convert dollars to microdollars
 */
function microDollars(float $dollars): int
{
    return (int) ($dollars * 1_000_000);
}

/**
 * Convert hours to seconds
 */
function computeSeconds(float $hours): int
{
    return (int) ($hours * 3600);
}

/**
 * Create a budget from human-readable values
 */
function createBudget(CreateBudgetOptions $options): TokenBudget
{
    $windowStart = $options->windowStart ?? new \DateTimeImmutable();

    return new TokenBudget(
        ai: microDollars($options->aiDollars),
        compute: computeSeconds($options->computeHours),
        window: $options->window,
        windowStart: $windowStart->format(\DateTimeInterface::ATOM),
    );
}

/**
 * Extract project ID from HMAC key
 * Format: hk_live_SECRET_PROJECTID or hk_test_SECRET_PROJECTID
 */
function extractProjectId(string $hmacKey): string
{
    $parts = explode('_', $hmacKey);
    if (count($parts) >= 4) {
        return $parts[3];
    }
    // If not in expected format, return as-is (might be a raw project ID)
    return $hmacKey;
}

/**
 * Create a JWT token for authentication
 */
function createToken(CreateTokenOptions $options): string
{
    $now = time();

    $payload = [
        'sub' => $options->userId,
        'iss' => $options->projectId,
        'iat' => $now,
        'exp' => $now + $options->expiresIn,
    ];

    if ($options->budget !== null) {
        $payload['budget'] = $options->budget->toArray();
    }

    if ($options->permissions !== null) {
        $payload['permissions'] = $options->permissions->toArray();
    }

    if ($options->sdkConfig !== null) {
        $payload['sdkConfig'] = $options->sdkConfig;
    }

    return JWT::encode($payload, $options->secret, 'HS256');
}

/**
 * Decode a JWT token without verification
 */
function decodeToken(string $token): array
{
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        throw new \InvalidArgumentException('Invalid token format');
    }

    $payload = base64_decode(strtr($parts[1], '-_', '+/'));
    return json_decode($payload, true);
}

/**
 * Verify a JWT token
 */
function verifyToken(string $token, string $secret): array
{
    $decoded = JWT::decode($token, new Key($secret, 'HS256'));
    return (array) $decoded;
}

/**
 * Check if a token is expired
 */
function isTokenExpired(string $token): bool
{
    $payload = decodeToken($token);
    $exp = $payload['exp'] ?? 0;
    return $exp < time();
}

/**
 * Get token expiration time
 */
function getTokenExpiration(string $token): ?\DateTimeInterface
{
    $payload = decodeToken($token);
    $exp = $payload['exp'] ?? null;

    if ($exp === null) {
        return null;
    }

    return (new \DateTimeImmutable())->setTimestamp($exp);
}

/**
 * Get budget from token
 */
function getTokenBudget(string $token): ?TokenBudget
{
    $payload = decodeToken($token);
    $budget = $payload['budget'] ?? null;

    if ($budget === null) {
        return null;
    }

    return new TokenBudget(
        ai: $budget['ai'] ?? 0,
        compute: $budget['compute'] ?? 0,
        window: BudgetWindow::from($budget['window'] ?? 'hour'),
        windowStart: $budget['windowStart'] ?? '',
    );
}
