<?php

declare(strict_types=1);

namespace ChuckyCloud\Sdk\Tools;

/**
 * MCP server type
 */
enum McpServerType: string
{
    case STDIO = 'stdio';
    case SSE = 'sse';
    case HTTP = 'http';
}

/**
 * Base interface for MCP server definitions
 */
interface McpServerDefinition
{
    public function getName(): string;
    public function toArray(): array;
}

/**
 * MCP server with client-side tools
 */
class McpClientToolsServer implements McpServerDefinition
{
    /**
     * @param ToolDefinition[] $tools
     */
    public function __construct(
        public readonly string $name,
        public readonly string $version = '1.0.0',
        public readonly array $tools = [],
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'version' => $this->version,
            'tools' => array_map(fn(ToolDefinition $t) => $t->toArray(), $this->tools),
        ];
    }

    /**
     * Get tool handlers for local execution
     * @return array<string, callable>
     */
    public function getHandlers(): array
    {
        $handlers = [];
        foreach ($this->tools as $tool) {
            if ($tool->handler !== null) {
                $handlers[$tool->name] = $tool->handler;
            }
        }
        return $handlers;
    }
}

/**
 * MCP stdio server configuration
 */
class McpStdioServer implements McpServerDefinition
{
    /**
     * @param string[] $args
     * @param array<string, string>|null $env
     */
    public function __construct(
        public readonly string $name,
        public readonly string $command,
        public readonly array $args = [],
        public readonly ?array $env = null,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function toArray(): array
    {
        $data = [
            'name' => $this->name,
            'type' => 'stdio',
            'command' => $this->command,
            'args' => $this->args,
        ];

        if ($this->env !== null) {
            $data['env'] = $this->env;
        }

        return $data;
    }
}

/**
 * MCP SSE server configuration
 */
class McpSseServer implements McpServerDefinition
{
    /**
     * @param array<string, string>|null $headers
     */
    public function __construct(
        public readonly string $name,
        public readonly string $url,
        public readonly ?array $headers = null,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function toArray(): array
    {
        $data = [
            'name' => $this->name,
            'type' => 'sse',
            'url' => $this->url,
        ];

        if ($this->headers !== null) {
            $data['headers'] = $this->headers;
        }

        return $data;
    }
}

/**
 * MCP HTTP server configuration
 */
class McpHttpServer implements McpServerDefinition
{
    /**
     * @param array<string, string>|null $headers
     */
    public function __construct(
        public readonly string $name,
        public readonly string $url,
        public readonly ?array $headers = null,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function toArray(): array
    {
        $data = [
            'name' => $this->name,
            'type' => 'http',
            'url' => $this->url,
        ];

        if ($this->headers !== null) {
            $data['headers'] = $this->headers;
        }

        return $data;
    }
}

/**
 * MCP server builder
 */
class McpServerBuilder
{
    private string $name;
    private string $version = '1.0.0';
    /** @var ToolDefinition[] */
    private array $tools = [];

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function version(string $version): self
    {
        $this->version = $version;
        return $this;
    }

    public function addTool(ToolDefinition $tool): self
    {
        $this->tools[] = $tool;
        return $this;
    }

    /**
     * Add multiple tools
     * @param ToolDefinition[] $tools
     */
    public function addTools(array $tools): self
    {
        $this->tools = array_merge($this->tools, $tools);
        return $this;
    }

    public function build(): McpClientToolsServer
    {
        return new McpClientToolsServer(
            name: $this->name,
            version: $this->version,
            tools: $this->tools,
        );
    }
}

/**
 * Create a new MCP server builder
 */
function mcpServer(string $name): McpServerBuilder
{
    return new McpServerBuilder($name);
}

/**
 * Create a stdio MCP server
 */
function stdioServer(string $name, string $command, string ...$args): McpStdioServer
{
    return new McpStdioServer($name, $command, $args);
}

/**
 * Create an SSE MCP server
 */
function sseServer(string $name, string $url, ?array $headers = null): McpSseServer
{
    return new McpSseServer($name, $url, $headers);
}

/**
 * Create an HTTP MCP server
 */
function httpServer(string $name, string $url, ?array $headers = null): McpHttpServer
{
    return new McpHttpServer($name, $url, $headers);
}
