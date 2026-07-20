<?php

namespace Labelgrup\LaravelUtilities\AI\Mcp\Schemas\Interfaces;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;

interface OutputSchemaInterface
{
    /**
     * Raw schema-typed values. If the owning #[OutputSchema] attribute declares a wrap
     * key, this becomes the properties of an object nested under that key; otherwise it
     * is returned as-is (already the final top-level array, e.g. multiple keys). When the
     * attribute declares scalar:true, this returns a single Type instead (the array item
     * type), used as-is with no object() wrapping — for responses that are a plain array
     * of scalars rather than objects.
     */
    public static function shape(JsonSchema $schema): array|Type;
}
