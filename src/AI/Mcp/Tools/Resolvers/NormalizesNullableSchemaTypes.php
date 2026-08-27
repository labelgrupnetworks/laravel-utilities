<?php

namespace Labelgrup\LaravelUtilities\AI\Mcp\Tools\Resolvers;

/**
 * Laravel's JsonSchema serializer renders a nullable() field as `"type": [X, "null"]`.
 * That's valid JSON Schema, but several MCP clients only accept `type` as a single
 * string and either reject the whole tool or silently drop the constraint. This rewrites
 * any array `type` into an `anyOf` of single-type branches before the schema is sent.
 */
trait NormalizesNullableSchemaTypes
{
    public function toArray(): array
    {
        $result = parent::toArray();

        foreach (['inputSchema', 'outputSchema'] as $key) {
            if (isset($result[$key]) && is_array($result[$key])) {
                $result[$key] = $this->normalizeNullableTypes($result[$key]);
            }
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function normalizeNullableTypes(array $node): array
    {
        if (isset($node['type']) && is_array($node['type'])) {
            $node['anyOf'] = array_map(fn (string $type) => ['type' => $type], $node['type']);
            unset($node['type']);
        }

        if (isset($node['properties']) && is_array($node['properties'])) {
            $node['properties'] = array_map(
                fn (array $property) => $this->normalizeNullableTypes($property),
                $node['properties']
            );
        }

        if (isset($node['items']) && is_array($node['items'])) {
            $node['items'] = $this->normalizeNullableTypes($node['items']);
        }

        return $node;
    }
}
