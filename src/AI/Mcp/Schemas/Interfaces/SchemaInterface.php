<?php

namespace Labelgrup\LaravelUtilities\AI\Mcp\Schemas\Interfaces;

use Illuminate\Contracts\JsonSchema\JsonSchema;

interface SchemaInterface
{
    public static function schema(JsonSchema $schema): array;
}
