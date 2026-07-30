<?php

namespace Labelgrup\LaravelUtilities\AI\Mcp\Resources\Abstracts;

use Labelgrup\LaravelUtilities\AI\Mcp\Resources\ServerToolResolver;
use Labelgrup\LaravelUtilities\AI\Mcp\Resources\ToolsSchemaCatalogBuilder;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Resource;

abstract class AbstractToolsSchemaCatalogResource extends Resource
{
    protected string $mimeType = 'application/json';

    /**
     * @return class-string<\Laravel\Mcp\Server\Server>
     */
    abstract protected function serverClass(): string;

    public function handle(Request $request): Response
    {
        $catalog = ToolsSchemaCatalogBuilder::build(
            ServerToolResolver::toolClasses($this->serverClass()),
            $this->decorateEntry(...),
        );

        return Response::text(json_encode(
            $catalog,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ) . "\n");
    }

    /**
     * Extension point for a consuming project to enrich each catalog entry
     * (e.g. project-specific grouping or scope metadata) before it's
     * serialized. Receives the raw tool class name separately so a project
     * can derive things from it (e.g. a domain grouping) without `class`
     * itself ever becoming part of the serialized entry. Identity by default.
     *
     * @param  array<string, mixed>  $entry
     * @param  class-string<\Laravel\Mcp\Server\Tool>  $tool_class
     * @return array<string, mixed>
     */
    protected function decorateEntry(array $entry, string $tool_class): array
    {
        return $entry;
    }
}
