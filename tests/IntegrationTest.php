<?php

declare(strict_types=1);

/**
 * Integration tests for Chucky PHP SDK.
 *
 * Requires environment variables:
 * - CHUCKY_PROJECT_ID: Project ID from Chucky portal
 * - CHUCKY_HMAC_KEY: HMAC key for the project
 */

namespace ChuckyCloud\Sdk\Tests;

use ChuckyCloud\Sdk\Client\ChuckyClient;
use ChuckyCloud\Sdk\Types\AssistantMessage;
use ChuckyCloud\Sdk\Types\BudgetWindow;
use ChuckyCloud\Sdk\Types\ClientOptions;
use ChuckyCloud\Sdk\Types\Model;
use ChuckyCloud\Sdk\Types\ResultMessage;
use ChuckyCloud\Sdk\Types\SessionOptions;
use ChuckyCloud\Sdk\Utils\CreateBudgetOptions;
use ChuckyCloud\Sdk\Utils\CreateTokenOptions;
use PHPUnit\Framework\TestCase;
use React\EventLoop\Loop;

use function ChuckyCloud\Sdk\Tools\mcpServer;
use function ChuckyCloud\Sdk\Tools\schema;
use function ChuckyCloud\Sdk\Tools\textResult;
use function ChuckyCloud\Sdk\Tools\tool;
use function ChuckyCloud\Sdk\Utils\createBudget;
use function ChuckyCloud\Sdk\Utils\createToken;

class IntegrationTest extends TestCase
{
    private static ?string $projectId = null;
    private static ?string $hmacKey = null;

    public static function setUpBeforeClass(): void
    {
        self::$projectId = getenv('CHUCKY_PROJECT_ID') ?: null;
        self::$hmacKey = getenv('CHUCKY_HMAC_KEY') ?: null;
    }

    private function getTestToken(): string
    {
        if (empty(self::$projectId) || empty(self::$hmacKey)) {
            $this->markTestSkipped('Missing CHUCKY_PROJECT_ID or CHUCKY_HMAC_KEY');
        }

        return createToken(new CreateTokenOptions(
            userId: 'test-user',
            projectId: self::$projectId,
            secret: self::$hmacKey,
            budget: createBudget(new CreateBudgetOptions(
                aiDollars: 1.0,
                computeHours: 1.0,
                window: BudgetWindow::DAY,
            )),
            expiresIn: 3600,
        ));
    }

    public function testTokenCreation(): void
    {
        if (empty(self::$projectId) || empty(self::$hmacKey)) {
            $this->markTestSkipped('Missing CHUCKY_PROJECT_ID or CHUCKY_HMAC_KEY');
        }

        $token = createToken(new CreateTokenOptions(
            userId: 'test-user',
            projectId: self::$projectId,
            secret: self::$hmacKey,
            budget: createBudget(new CreateBudgetOptions(
                aiDollars: 1.0,
                computeHours: 1.0,
                window: BudgetWindow::DAY,
            )),
            expiresIn: 3600,
        ));

        $this->assertNotEmpty($token);

        // JWT should have 3 parts
        $parts = explode('.', $token);
        $this->assertCount(3, $parts, 'Token should be a valid JWT with 3 parts');
    }

    public function testSimplePrompt(): void
    {
        $token = $this->getTestToken();
        $loop = Loop::get();
        $result = null;
        $error = null;

        $client = new ChuckyClient(
            new ClientOptions(
                token: $token,
                debug: false,
            ),
            $loop
        );

        $session = $client->createSession(new SessionOptions(
            model: Model::CLAUDE_SONNET,
            maxTurns: 3,
        ));

        $session->connect()->then(
            function () use ($session, $client, &$result, &$error) {
                $session->send('Say "hello test" and nothing else.')->then(
                    function () use ($session, $client, &$result, &$error) {
                        $this->receiveUntilResult($session, $client, $result, $error);
                    },
                    function (\Exception $e) use ($client, &$error) {
                        $error = $e;
                        $client->stop();
                    }
                );
            },
            function (\Exception $e) use ($client, &$error) {
                $error = $e;
                $client->stop();
            }
        );

        // Set timeout
        $loop->addTimer(120, function () use ($client, &$error) {
            $error = new \Exception('Test timeout');
            $client->stop();
        });

        $client->run();

        $this->assertNull($error, "Test failed with error: " . ($error?->getMessage() ?? ''));
        $this->assertNotNull($result, 'Expected result message');
        $this->assertInstanceOf(ResultMessage::class, $result);
        $this->assertStringContainsStringIgnoringCase('hello', $result->result);

    }

    public function testStructuredOutput(): void
    {
        // Wait for previous test's session to be released
        sleep(10);

        $token = $this->getTestToken();
        $loop = Loop::get();
        $result = null;
        $error = null;

        $client = new ChuckyClient(
            new ClientOptions(
                token: $token,
                debug: false,
            ),
            $loop
        );

        $session = $client->createSession(new SessionOptions(
            model: Model::CLAUDE_SONNET,
            maxTurns: 3,
        ));

        $session->connect()->then(
            function () use ($session, $client, &$result, &$error) {
                $session->send('What is 2 + 2? Answer with just the number.')->then(
                    function () use ($session, $client, &$result, &$error) {
                        $this->receiveUntilResult($session, $client, $result, $error);
                    },
                    function (\Exception $e) use ($client, &$error) {
                        $error = $e;
                        $client->stop();
                    }
                );
            },
            function (\Exception $e) use ($client, &$error) {
                $error = $e;
                $client->stop();
            }
        );

        // Set timeout
        $loop->addTimer(120, function () use ($client, &$error) {
            $error = new \Exception('Test timeout');
            $client->stop();
        });

        $client->run();

        $this->assertNull($error, "Test failed with error: " . ($error?->getMessage() ?? ''));
        $this->assertNotNull($result, 'Expected result message');
        $this->assertInstanceOf(ResultMessage::class, $result);
        $this->assertStringContainsString('4', $result->result);

    }

    public function testMcpToolExecution(): void
    {
        // Wait for previous test's session to be released
        sleep(10);

        $token = $this->getTestToken();
        $loop = Loop::get();
        $result = null;
        $error = null;

        // Track tool calls
        $toolWasCalled = false;
        $toolInputA = null;
        $toolInputB = null;

        // Create add tool
        $addTool = tool(
            name: 'add',
            description: 'Add two numbers together',
            schema: schema()
                ->integer('a', 'First number')
                ->integer('b', 'Second number')
                ->required('a', 'b')
                ->build(),
            handler: function (array $input) use (&$toolWasCalled, &$toolInputA, &$toolInputB) {
                $toolWasCalled = true;
                $toolInputA = (int) $input['a'];
                $toolInputB = (int) $input['b'];
                $sum = $toolInputA + $toolInputB;
                return textResult("The sum of {$toolInputA} and {$toolInputB} is {$sum}");
            }
        );

        // Create MCP server
        $mcpServer = mcpServer('calculator')
            ->addTool($addTool)
            ->build();

        $client = new ChuckyClient(
            new ClientOptions(
                token: $token,
                debug: false,
            ),
            $loop
        );

        $session = $client->createSession(new SessionOptions(
            model: Model::CLAUDE_SONNET,
            maxTurns: 5,
            mcpServers: [$mcpServer],
        ));

        $session->connect()->then(
            function () use ($session, $client, &$result, &$error) {
                $session->send('Use the add tool to calculate 7 + 15. Report the result.')->then(
                    function () use ($session, $client, &$result, &$error) {
                        $this->receiveUntilResult($session, $client, $result, $error);
                    },
                    function (\Exception $e) use ($client, &$error) {
                        $error = $e;
                        $client->stop();
                    }
                );
            },
            function (\Exception $e) use ($client, &$error) {
                $error = $e;
                $client->stop();
            }
        );

        // Set timeout
        $loop->addTimer(120, function () use ($client, &$error) {
            $error = new \Exception('Test timeout');
            $client->stop();
        });

        $client->run();

        $this->assertNull($error, "Test failed with error: " . ($error?->getMessage() ?? ''));
        $this->assertTrue($toolWasCalled, 'Tool was not called');
        $this->assertEquals(7, $toolInputA, 'Expected first input to be 7');
        $this->assertEquals(15, $toolInputB, 'Expected second input to be 15');
        $this->assertNotNull($result, 'Expected result message');
        $this->assertInstanceOf(ResultMessage::class, $result);
        $this->assertStringContainsString('22', $result->result);

    }

    private function receiveUntilResult($session, $client, &$result, &$error): void
    {
        $receiveNext = function () use (&$receiveNext, $session, $client, &$result, &$error) {
            $session->receive()->then(
                function ($msg) use (&$receiveNext, $session, $client, &$result) {
                    if ($msg instanceof ResultMessage) {
                        $result = $msg;
                        $session->close();
                        $client->stop();
                        return;
                    }
                    // Continue receiving
                    $receiveNext();
                },
                function (\Exception $e) use ($client, &$error) {
                    $error = $e;
                    $client->stop();
                }
            );
        };
        $receiveNext();
    }
}
