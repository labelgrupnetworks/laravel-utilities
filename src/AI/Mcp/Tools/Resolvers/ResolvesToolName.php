<?php

namespace Labelgrup\LaravelUtilities\AI\Mcp\Tools\Resolvers;

use Illuminate\Support\Str;
use Laravel\Mcp\Server\Attributes\Name;
use ReflectionClass;

/**
 * Reads a Tool's own #[Name] attribute statically, without instantiating it, so other
 * schemas/helpers can reference "the tool named X" (e.g. in a description) without
 * hardcoding the string and re-parsing it manually via ReflectionClass each time.
 */
trait ResolvesToolName
{
    public static function toolName(): string
    {
        $attribute = (new ReflectionClass(static::class))->getAttributes(Name::class)[0] ?? null;

        return $attribute?->newInstance()->value ?? Str::kebab(class_basename(static::class));
    }
}
