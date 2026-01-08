<?php

declare(strict_types=1);

/**
 * Chucky SDK for PHP
 *
 * Main entry point and convenience functions.
 *
 * @package ChuckyCloud\Sdk
 */

namespace ChuckyCloud\Sdk;

// Re-export main classes
use ChuckyCloud\Sdk\Client\ChuckyClient;
use ChuckyCloud\Sdk\Client\Session;
use ChuckyCloud\Sdk\Types\ClientOptions;
use ChuckyCloud\Sdk\Types\SessionOptions;
use ChuckyCloud\Sdk\Types\Model;
use ChuckyCloud\Sdk\Types\BudgetWindow;
use ChuckyCloud\Sdk\Utils\CreateBudgetOptions;
use ChuckyCloud\Sdk\Utils\CreateTokenOptions;
use ChuckyCloud\Sdk\Utils\TokenBudget;

use function ChuckyCloud\Sdk\Utils\createBudget as utilsCreateBudget;
use function ChuckyCloud\Sdk\Utils\createToken as utilsCreateToken;

/**
 * Create a new Chucky client
 *
 * @param string $token JWT authentication token
 * @param array{
 *   baseUrl?: string,
 *   debug?: bool,
 *   timeout?: float,
 *   keepAliveInterval?: float,
 * } $options Additional options
 */
function createClient(string $token, array $options = []): ChuckyClient
{
    return new ChuckyClient(new ClientOptions(
        token: $token,
        baseUrl: $options['baseUrl'] ?? 'wss://conjure.chucky.cloud/ws',
        debug: $options['debug'] ?? false,
        timeout: $options['timeout'] ?? 60.0,
        keepAliveInterval: $options['keepAliveInterval'] ?? 300.0,
    ));
}

/**
 * Create a budget from human-readable values
 *
 * @param array{
 *   aiDollars?: float,
 *   computeHours?: float,
 *   window?: BudgetWindow|string,
 *   windowStart?: \DateTimeInterface,
 * } $options Budget options
 */
function createBudget(array $options): TokenBudget
{
    $window = $options['window'] ?? BudgetWindow::HOUR;
    if (is_string($window)) {
        $window = BudgetWindow::from($window);
    }

    return utilsCreateBudget(new CreateBudgetOptions(
        aiDollars: $options['aiDollars'] ?? 0.0,
        computeHours: $options['computeHours'] ?? 0.0,
        window: $window,
        windowStart: $options['windowStart'] ?? null,
    ));
}

/**
 * Create a JWT token for authentication
 *
 * @param array{
 *   userId: string,
 *   projectId: string,
 *   secret: string,
 *   budget?: TokenBudget,
 *   expiresIn?: int,
 * } $options Token options
 */
function createToken(array $options): string
{
    return utilsCreateToken(new CreateTokenOptions(
        userId: $options['userId'],
        projectId: $options['projectId'],
        secret: $options['secret'],
        budget: $options['budget'] ?? null,
        expiresIn: $options['expiresIn'] ?? 3600,
    ));
}
