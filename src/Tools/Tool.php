<?php

declare(strict_types=1);

namespace ChuckyCloud\Sdk\Tools;

use ChuckyCloud\Sdk\Types\ExecuteLocation;

/**
 * Tool result content types
 */
class TextContent
{
    public function __construct(
        public readonly string $text,
    ) {}

    public function toArray(): array
    {
        return [
            'type' => 'text',
            'text' => $this->text,
        ];
    }
}

class ImageContent
{
    public function __construct(
        public readonly string $data,
        public readonly string $mimeType,
    ) {}

    public function toArray(): array
    {
        return [
            'type' => 'image',
            'data' => $this->data,
            'mimeType' => $this->mimeType,
        ];
    }
}

class ResourceContent
{
    public function __construct(
        public readonly string $uri,
        public readonly ?string $mimeType = null,
        public readonly ?string $text = null,
        public readonly ?string $blob = null,
    ) {}

    public function toArray(): array
    {
        $data = [
            'type' => 'resource',
            'uri' => $this->uri,
        ];

        if ($this->mimeType !== null) {
            $data['mimeType'] = $this->mimeType;
        }
        if ($this->text !== null) {
            $data['text'] = $this->text;
        }
        if ($this->blob !== null) {
            $data['blob'] = $this->blob;
        }

        return $data;
    }
}

/**
 * Tool execution result
 */
class ToolResult
{
    /**
     * @param array<TextContent|ImageContent|ResourceContent|array> $content
     */
    public function __construct(
        public readonly array $content,
        public readonly bool $isError = false,
    ) {}

    public function toArray(): array
    {
        $content = array_map(
            fn($item) => $item instanceof TextContent || $item instanceof ImageContent || $item instanceof ResourceContent
                ? $item->toArray()
                : $item,
            $this->content
        );

        return [
            'content' => $content,
            'isError' => $this->isError,
        ];
    }
}

/**
 * Create a text result
 */
function textResult(string $text): ToolResult
{
    return new ToolResult([new TextContent($text)]);
}

/**
 * Create an error result
 */
function errorResult(string $message): ToolResult
{
    return new ToolResult([new TextContent($message)], true);
}

/**
 * Create an image result
 */
function imageResult(string $base64Data, string $mimeType): ToolResult
{
    return new ToolResult([new ImageContent($base64Data, $mimeType)]);
}

/**
 * Create a resource result
 */
function resourceResult(string $uri, ?string $mimeType = null, ?string $text = null): ToolResult
{
    return new ToolResult([new ResourceContent($uri, $mimeType, $text)]);
}

/**
 * JSON Schema property
 */
class SchemaProperty
{
    public function __construct(
        public readonly string $type,
        public readonly ?string $description = null,
        public readonly ?array $enum = null,
        public readonly ?SchemaProperty $items = null,
    ) {}

    public function toArray(): array
    {
        $data = ['type' => $this->type];

        if ($this->description !== null) {
            $data['description'] = $this->description;
        }
        if ($this->enum !== null) {
            $data['enum'] = $this->enum;
        }
        if ($this->items !== null) {
            $data['items'] = $this->items->toArray();
        }

        return $data;
    }
}

/**
 * Tool input schema
 */
class ToolInputSchema
{
    /**
     * @param array<string, SchemaProperty|array> $properties
     * @param string[] $required
     */
    public function __construct(
        public readonly array $properties = [],
        public readonly array $required = [],
    ) {}

    public function toArray(): array
    {
        $props = [];
        foreach ($this->properties as $name => $prop) {
            $props[$name] = $prop instanceof SchemaProperty ? $prop->toArray() : $prop;
        }

        return [
            'type' => 'object',
            'properties' => $props,
            'required' => $this->required,
        ];
    }
}

/**
 * Schema builder for fluent API
 */
class SchemaBuilder
{
    /** @var array<string, SchemaProperty|array> */
    private array $properties = [];

    /** @var string[] */
    private array $required = [];

    public function string(string $name, string $description): self
    {
        $this->properties[$name] = new SchemaProperty('string', $description);
        return $this;
    }

    public function number(string $name, string $description): self
    {
        $this->properties[$name] = new SchemaProperty('number', $description);
        return $this;
    }

    public function integer(string $name, string $description): self
    {
        $this->properties[$name] = new SchemaProperty('integer', $description);
        return $this;
    }

    public function boolean(string $name, string $description): self
    {
        $this->properties[$name] = new SchemaProperty('boolean', $description);
        return $this;
    }

    public function enum(string $name, string $description, string ...$values): self
    {
        $this->properties[$name] = new SchemaProperty('string', $description, $values);
        return $this;
    }

    public function array(string $name, string $description, SchemaProperty $items): self
    {
        $this->properties[$name] = new SchemaProperty('array', $description, null, $items);
        return $this;
    }

    public function property(string $name, SchemaProperty|array $prop): self
    {
        $this->properties[$name] = $prop;
        return $this;
    }

    public function required(string ...$names): self
    {
        $this->required = array_merge($this->required, $names);
        return $this;
    }

    public function build(): ToolInputSchema
    {
        return new ToolInputSchema($this->properties, $this->required);
    }
}

/**
 * Create a new schema builder
 */
function schema(): SchemaBuilder
{
    return new SchemaBuilder();
}

/**
 * Tool handler callback type
 * @var callable(array<string, mixed>): ToolResult
 */

/**
 * Tool definition
 */
class ToolDefinition
{
    /**
     * @param callable|null $handler Tool handler function
     */
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly ToolInputSchema|array $inputSchema,
        public readonly mixed $handler = null,
        public readonly ExecuteLocation $executeIn = ExecuteLocation::SERVER,
    ) {}

    public function toArray(): array
    {
        $schema = $this->inputSchema instanceof ToolInputSchema
            ? $this->inputSchema->toArray()
            : $this->inputSchema;

        $data = [
            'name' => $this->name,
            'description' => $this->description,
            'inputSchema' => $schema,
        ];

        // Mark for client-side execution if handler is set
        if ($this->handler !== null) {
            $data['executeIn'] = 'client';
        }

        return $data;
    }
}

/**
 * Create a tool definition
 */
function tool(
    string $name,
    string $description,
    ToolInputSchema|array $schema,
    ?callable $handler = null,
): ToolDefinition {
    return new ToolDefinition(
        name: $name,
        description: $description,
        inputSchema: $schema,
        handler: $handler,
        executeIn: $handler !== null ? ExecuteLocation::CLIENT : ExecuteLocation::SERVER,
    );
}

/**
 * Create a browser/client-side tool
 */
function browserTool(
    string $name,
    string $description,
    ToolInputSchema|array $schema,
    callable $handler,
): ToolDefinition {
    return new ToolDefinition(
        name: $name,
        description: $description,
        inputSchema: $schema,
        handler: $handler,
        executeIn: ExecuteLocation::BROWSER,
    );
}

/**
 * Create a server-side tool
 */
function serverTool(
    string $name,
    string $description,
    ToolInputSchema|array $schema,
    ?callable $handler = null,
): ToolDefinition {
    return new ToolDefinition(
        name: $name,
        description: $description,
        inputSchema: $schema,
        handler: $handler,
        executeIn: ExecuteLocation::SERVER,
    );
}
