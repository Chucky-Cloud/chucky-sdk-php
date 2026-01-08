# Chucky SDK for PHP

Official PHP SDK for [Chucky](https://chucky.cloud) - Claude Code sandbox platform.

## Requirements

- PHP 8.1+
- Composer

## Installation

```bash
composer require chucky-cloud/sdk
```

## Quick Start

### Create a Token (Server-Side)

```php
<?php

use function ChuckyCloud\Sdk\createToken;
use function ChuckyCloud\Sdk\createBudget;
use ChuckyCloud\Sdk\Types\BudgetWindow;

// Create a token for a user
$token = createToken([
    'userId' => 'user-123',
    'projectId' => $_ENV['CHUCKY_PROJECT_ID'],
    'secret' => $_ENV['CHUCKY_HMAC_SECRET'],
    'budget' => createBudget([
        'aiDollars' => 1.0,
        'computeHours' => 0.5,
        'window' => BudgetWindow::DAY,
    ]),
    'expiresIn' => 3600,
]);
```

### Use the SDK

```php
<?php

use function ChuckyCloud\Sdk\createClient;
use ChuckyCloud\Sdk\Types\SessionOptions;
use ChuckyCloud\Sdk\Types\Model;
use ChuckyCloud\Sdk\Types\AssistantMessage;
use ChuckyCloud\Sdk\Types\ResultMessage;

// Create client
$client = createClient($token, ['debug' => true]);

// Create session
$session = $client->createSession(new SessionOptions(
    model: Model::CLAUDE_SONNET,
    maxTurns: 5,
));

// Connect and send message
$session->connect()->then(function () use ($session, $client) {
    $session->send('What is 2 + 2?')->then(function () use ($session, $client) {
        // Receive messages
        $receiveNext = function () use (&$receiveNext, $session, $client) {
            $session->receive()->then(function ($msg) use (&$receiveNext, $session, $client) {
                if ($msg instanceof AssistantMessage) {
                    echo "Assistant: " . $msg->getText() . "\n";
                }

                if ($msg instanceof ResultMessage) {
                    echo "Result: " . $msg->result . "\n";
                    echo "Cost: $" . $msg->totalCostUsd . "\n";
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
```

### With Tools

```php
<?php

use function ChuckyCloud\Sdk\Tools\tool;
use function ChuckyCloud\Sdk\Tools\textResult;
use function ChuckyCloud\Sdk\Tools\schema;
use function ChuckyCloud\Sdk\Tools\mcpServer;
use ChuckyCloud\Sdk\Types\SessionOptions;

// Define a calculator tool
$calculatorTool = tool(
    name: 'calculator',
    description: 'Perform arithmetic calculations',
    schema: schema()
        ->enum('operation', 'Operation', 'add', 'subtract', 'multiply', 'divide')
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
        };

        return textResult("Result: $result");
    }
);

// Create MCP server with tools
$mcpServer = mcpServer('calculator-server')
    ->addTool($calculatorTool)
    ->build();

// Create session with tools
$session = $client->createSession(new SessionOptions(
    model: Model::CLAUDE_SONNET,
    mcpServers: [$mcpServer],
));
```

## API Reference

### Client

```php
// Create client
$client = new ChuckyClient(new ClientOptions(
    token: $token,
    baseUrl: 'wss://conjure.chucky.cloud/ws',
    debug: false,
    timeout: 60.0,
));

// Create session
$session = $client->createSession($options);

// Resume session
$session = $client->resumeSession($sessionId, $options);

// Close all sessions
$client->close();
```

### Session

```php
// Connect
$session->connect()->then(fn() => ...);

// Send message
$session->send('Hello')->then(fn() => ...);

// Receive messages
$session->receive()->then(fn($msg) => ...);

// Close session
$session->close();
```

### Token Utilities

```php
use function ChuckyCloud\Sdk\Utils\createToken;
use function ChuckyCloud\Sdk\Utils\createBudget;
use function ChuckyCloud\Sdk\Utils\decodeToken;
use function ChuckyCloud\Sdk\Utils\verifyToken;
use function ChuckyCloud\Sdk\Utils\isTokenExpired;

// Create token
$token = createToken(new CreateTokenOptions(...));

// Create budget
$budget = createBudget(new CreateBudgetOptions(...));

// Decode without verification
$payload = decodeToken($token);

// Verify signature
$payload = verifyToken($token, $secret);

// Check expiration
$expired = isTokenExpired($token);
```

### Tools

```php
use function ChuckyCloud\Sdk\Tools\tool;
use function ChuckyCloud\Sdk\Tools\browserTool;
use function ChuckyCloud\Sdk\Tools\serverTool;
use function ChuckyCloud\Sdk\Tools\textResult;
use function ChuckyCloud\Sdk\Tools\errorResult;
use function ChuckyCloud\Sdk\Tools\imageResult;
use function ChuckyCloud\Sdk\Tools\schema;

// Create tool with handler
$myTool = tool('name', 'description', $schema, $handler);

// Schema builder
$schema = schema()
    ->string('name', 'User name')
    ->number('age', 'User age')
    ->boolean('active', 'Is active')
    ->enum('role', 'User role', 'admin', 'user')
    ->required('name', 'role')
    ->build();

// Results
return textResult('Success!');
return errorResult('Something went wrong');
return imageResult($base64, 'image/png');
```

### MCP Servers

```php
use function ChuckyCloud\Sdk\Tools\mcpServer;
use function ChuckyCloud\Sdk\Tools\stdioServer;
use function ChuckyCloud\Sdk\Tools\sseServer;

// Client-side tools server
$server = mcpServer('my-server')
    ->version('1.0.0')
    ->addTool($tool1)
    ->addTool($tool2)
    ->build();

// Stdio server
$server = stdioServer('fs', 'npx', '-y', '@modelcontextprotocol/server-filesystem');

// SSE server
$server = sseServer('remote', 'https://mcp.example.com/sse');
```

## Error Handling

```php
use ChuckyCloud\Sdk\Types\ChuckyException;
use ChuckyCloud\Sdk\Types\ConnectionException;
use ChuckyCloud\Sdk\Types\AuthenticationException;
use ChuckyCloud\Sdk\Types\BudgetExceededException;
use ChuckyCloud\Sdk\Types\SessionException;
use ChuckyCloud\Sdk\Types\TimeoutException;

try {
    // SDK operations...
} catch (BudgetExceededException $e) {
    echo "Budget exceeded: " . $e->getMessage();
} catch (AuthenticationException $e) {
    echo "Auth failed: " . $e->getMessage();
} catch (ChuckyException $e) {
    echo "Error: " . $e->getMessage();
}
```

## License

MIT
