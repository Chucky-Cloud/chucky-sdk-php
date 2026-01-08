<?php

declare(strict_types=1);

/**
 * Example of using the Chucky PHP SDK with tools
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
use function ChuckyCloud\Sdk\Tools\tool;
use function ChuckyCloud\Sdk\Tools\textResult;
use function ChuckyCloud\Sdk\Tools\schema;
use function ChuckyCloud\Sdk\Tools\mcpServer;

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

// Define calculator tool
$calculatorTool = tool(
    name: 'calculator',
    description: 'Perform arithmetic calculations',
    schema: schema()
        ->enum('operation', 'Operation to perform', 'add', 'subtract', 'multiply', 'divide')
        ->number('a', 'First operand')
        ->number('b', 'Second operand')
        ->required('operation', 'a', 'b')
        ->build(),
    handler: function (array $input) {
        $op = $input['operation'];
        $a = $input['a'];
        $b = $input['b'];

        $result = match ($op) {
            'add' => $a + $b,
            'subtract' => $a - $b,
            'multiply' => $a * $b,
            'divide' => $b !== 0 ? $a / $b : 'Error: Division by zero',
            default => 'Error: Unknown operation',
        };

        echo "[Tool Called] calculator({$op}, {$a}, {$b}) = {$result}\n";
        return textResult("Result: {$result}");
    }
);

// Create MCP server with tools
$mcpServer = mcpServer('calculator-server')
    ->version('1.0.0')
    ->addTool($calculatorTool)
    ->build();

// Create client
$client = new ChuckyClient(new ClientOptions(
    token: $token,
    debug: true,
));

// Create session with tools
$session = $client->createSession(new SessionOptions(
    model: Model::CLAUDE_SONNET,
    maxTurns: 5,
    mcpServers: [$mcpServer],
));

echo "Connecting...\n";

// Connect and send message
$session->connect()->then(function () use ($session, $client) {
    echo "Connected! Sending message...\n";

    $session->send('What is 15 multiplied by 7? Use the calculator tool.')->then(function () use ($session, $client) {
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
