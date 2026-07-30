<?php

namespace Labelgrup\LaravelUtilities\AI\Mcp\Resources;

use Illuminate\Container\Container;

/**
 * Builds the tools+schemas catalog for one MCP server, reusing Tool::toArray().
 *
 * Never filter $tool_classes by any per-caller eligibility/scope check — that
 * would make the catalog (and a diff taken across two snapshots) vary by
 * reader. Always list every class passed in.
 */
final class ToolsSchemaCatalogBuilder
{
    /**
     * @param  array<int, class-string<\Laravel\Mcp\Server\Tool>>  $tool_classes
     * @param  (callable(array<string, mixed>, class-string<\Laravel\Mcp\Server\Tool>): array<string, mixed>)|null  $decorate
     *         Optional per-entry enrichment hook, receiving the raw tool class name.
     * @return array<string, mixed>
     */
    public static function build(array $tool_classes, ?callable $decorate = null): array
    {
        $catalog = array_map(fn (string $tool_class) => self::entry($tool_class, $decorate), $tool_classes);

        usort($catalog, fn (array $a, array $b) => $a['name'] <=> $b['name']);

        return ['tools' => $catalog];
    }

    /**
     * @param  class-string<\Laravel\Mcp\Server\Tool>  $tool_class
     * @param  (callable(array<string, mixed>, class-string<\Laravel\Mcp\Server\Tool>): array<string, mixed>)|null  $decorate
     * @return array<string, mixed>
     */
    private static function entry(string $tool_class, ?callable $decorate): array
    {
        $data = Container::getInstance()->make($tool_class)->toArray();
        $annotations = (array) ($data['annotations'] ?? []);
        $entry = [
            'name' => $data['name'],
            'title' => $data['title'] ?? null,
            'description' => $data['description'] ?? null,
            'annotations' => [
                'readOnlyHint' => $annotations['readOnlyHint'] ?? false,
                'destructiveHint' => $annotations['destructiveHint'] ?? true,
                'idempotentHint' => $annotations['idempotentHint'] ?? false,
                'openWorldHint' => $annotations['openWorldHint'] ?? true,
            ],
            'inputSchema' => self::sortSchema($data['inputSchema'] ?? []),
            'outputSchema' => isset($data['outputSchema']) ? self::sortSchema($data['outputSchema']) : null,
        ];

        return $decorate ? $decorate($entry, $tool_class) : $entry;
    }

    /**
     * Recursively sorts `properties` keys alphabetically so schema field
     * reordering never shows up as a snapshot diff on its own.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private static function sortSchema(array $schema): array
    {
        if (isset($schema['properties']) && is_array($schema['properties'])) {
            ksort($schema['properties']);
            $schema['properties'] = array_map(
                fn (mixed $property) => is_array($property) ? self::sortSchema($property) : $property,
                $schema['properties'],
            );
        }

        if (isset($schema['items']) && is_array($schema['items'])) {
            $schema['items'] = self::sortSchema($schema['items']);
        }

        return $schema;
    }
}
