<?php

namespace Labelgrup\LaravelUtilities\AI\Mcp\Schemas\Attributes;

use Attribute;
use Labelgrup\LaravelUtilities\AI\Mcp\Schemas\Interfaces\OutputSchemaInterface;

/**
 * Declares which class provides the Tool's output schema. When $key is given, the
 * class's shape() is nested under that key (e.g. 'data'): as a single object by
 * default, or as an array of objects when $many is true. When $key is null,
 * shape()'s return is used as the final top-level array as-is. When $scalar is true,
 * shape() returns a single Type used directly (no object() wrapping) — for a response
 * that is a plain array of scalars (e.g. strings) rather than objects; requires $key.
 *
 * @see OutputSchemaInterface
 */
#[Attribute(Attribute::TARGET_CLASS)]
class OutputSchema
{
    /**
     * @param  class-string<OutputSchemaInterface>  $class
     */
    public function __construct(
        public string $class,
        public ?string $key = null,
        public ?string $description = null,
        public bool $many = false,
        public bool $scalar = false
    ) {
    }
}
