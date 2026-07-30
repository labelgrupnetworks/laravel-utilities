<?php

namespace Labelgrup\LaravelUtilities\AI\Mcp\Resources;

use ReflectionClass;

final class ServerToolResolver
{
    /**
     * Returns the tool classes registered on an MCP server without
     * constructing it (its constructor requires a Transport, unavailable
     * outside a real MCP request lifecycle).
     *
     * @param  class-string<\Laravel\Mcp\Server\Server>  $server_class
     * @return array<int, class-string<\Laravel\Mcp\Server\Tool>>
     */
    public static function toolClasses(string $server_class): array
    {
        return (new ReflectionClass($server_class))->getDefaultProperties()['tools'] ?? [];
    }
}
