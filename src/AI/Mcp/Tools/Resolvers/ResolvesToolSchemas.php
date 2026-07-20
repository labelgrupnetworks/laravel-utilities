<?php

namespace Labelgrup\LaravelUtilities\AI\Mcp\Tools\Resolvers;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use InvalidArgumentException;
use Labelgrup\LaravelUtilities\AI\Mcp\Schemas\Attributes\OutputSchema;
use Labelgrup\LaravelUtilities\AI\Mcp\Schemas\Attributes\Schema;
use ReflectionClass;

/**
 * Default schema()/outputSchema() for Tools that declare #[Schema] / #[OutputSchema]
 * instead of hand-writing the array. A Tool can still override either method directly;
 * that override always wins over this trait.
 */
trait ResolvesToolSchemas
{
    public function schema(JsonSchema $schema): array
    {
        $attributes = (new ReflectionClass($this))->getAttributes(Schema::class);

        if (!$attributes) {
            return [];
        }

        return array_merge(
            ...array_map(
                fn ($attribute) => $attribute->newInstance()->class::schema($schema),
                $attributes
            )
        );
    }

    public function outputSchema(JsonSchema $schema): array
    {
        $attribute = (new ReflectionClass($this))->getAttributes(OutputSchema::class)[0] ?? null;

        if (!$attribute) {
            return [];
        }

        $instance = $attribute->newInstance();

        if ($instance->many && $instance->key === null) {
            throw new InvalidArgumentException('OutputSchema with many:true requires a wrap key.');
        }

        if ($instance->scalar && $instance->key === null) {
            throw new InvalidArgumentException('OutputSchema with scalar:true requires a wrap key.');
        }

        $item = $instance->class::shape($schema);

        if ($instance->scalar) {
            $value = $instance->many ? $schema->array()->items($item) : $item;

            if ($instance->description !== null) {
                $value->description($instance->description);
            }

            return [$instance->key => $value];
        }

        if ($instance->key === null) {
            return $item;
        }

        $object = $schema->object($item);
        $value = $instance->many ? $schema->array()->items($object) : $object;

        if ($instance->description !== null) {
            $value->description($instance->description);
        }

        return [$instance->key => $value];
    }
}
