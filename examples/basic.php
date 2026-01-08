<?php

declare(strict_types=1);

/**
 * Basic example of using the Chucky PHP SDK
 */

require_once __DIR__ . '/../vendor/autoload.php';

use ChuckyCloud\Sdk\Client\ChuckyClient;
use ChuckyCloud\Sdk\Types\ClientOptions;
use ChuckyCloud\Sdk\Types\SessionOptions;
use ChuckyCloud\Sdk\Types\Model;
use ChuckyCloud\Sdk\Types\BudgetWindow;
use ChuckyCloud\Sdk\Types\AssistantMessage;
use ChuckyCloud\Sdk\Types\ResultMessage;
use ChuckyCloud\Sdk\Types\SystemMessage;
use ChuckyCloud\Sdk\Utils\CreateBudgetOptions;
use ChuckyCloud\Sdk\Utils\CreateTokenOptions;

use function ChuckyCloud\Sdk\Utils\createBudget;
use function ChuckyCloud\Sdk\Utils\createToken;

// Configuration
$projectId = getenv('CHUCKY_PROJECT_ID') ?: 'your-project-id';
$secret = getenv('CHUCKY_HMAC_SECRET') ?: 'hk_live_your_secret';

// Create a token
$token = createToken(new CreateTokenOptions(
    userId: 'test-user',
    projectId: $projectId,
    secret: $secret,
    budget: createBudget(new CreateBudgetOptions(
        aiDollars: 1.0,
        computeHours: 0.5,
        window: BudgetWindow::HOUR,
    )),
));

echo "Token created!\n";

// Create client
$client = new ChuckyClient(new ClientOptions(
    token: $token,
    debug: true,
));

// Create session
$session = $client->createSession(new SessionOptions(
    model: Model::CLAUDE_SONNET,
    maxTurns: 3,
));

echo "Connecting...\n";

// Connect and send message
$session->connect()->then(function () use ($session, $client) {
    echo "Connected! Sending message...\n";

    $session->send('What is 2 + 2? Reply with just the number.')->then(function () use ($session, $client) {
        echo "Message sent! Waiting for response...\n";

        // Message receive loop
        $receiveNext = function () use (&$receiveNext, $session, $client) {
            $session->receive()->then(function ($msg) use (&$receiveNext, $session, $client) {
                if ($msg instanceof SystemMessage) {
                    echo "[System] {$msg->subtype->value}\n";
                }

                if ($msg instanceof AssistantMessage) {
                    $text = $msg->getText();
                    if ($text) {
                        echo "[Assistant] {$text}\n";
                    }
                }

                if ($msg instanceof ResultMessage) {
                    echo "\n=== Result ===\n";
                    echo "Answer: {$msg->result}\n";
                    echo "Cost: \${$msg->totalCostUsd}\n";
                    echo "Turns: {$msg->numTurns}\n";
                    echo "Duration: {$msg->durationMs}ms\n";

                    $session->close();
                    $client->stop();
                    return;
                }

                // Continue receiving
                $receiveNext();
            });
        };

        $receiveNext();
    });
});

// Run the event loop
$client->run();

echo "\nDone!\n";
