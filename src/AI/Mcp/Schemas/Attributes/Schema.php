<?php

namespace Labelgrup\LaravelUtilities\AI\Mcp\Schemas\Attributes;

use Attribute;
use Labelgrup\LaravelUtilities\AI\Mcp\Schemas\Interfaces\SchemaInterface;

/**
 * Declares which class provides the Tool's input schema. Repeatable: when a Tool
 * declares more than one #[Schema], each class's schema() is merged (array_merge,
 * in declaration order) — used when the input combines a FormRequest with route
 * params that have no single owning class.
 *
 * @see SchemaInterface
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class Schema
{
    /**
     * @param  class-string<SchemaInterface>  $class
     */
    public function __construct(public string $class)
    {
    }
}
