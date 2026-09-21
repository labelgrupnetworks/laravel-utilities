<?php

namespace Labelgrup\LaravelUtilities\AI\Mcp\Tools\Attributes;

use Attribute;
use Labelgrup\LaravelUtilities\AI\Mcp\Tools\Interfaces\ToolAuthorizerInterface;

/**
 * Declares an authorizer that must approve the current context before the Tool
 * runs. Repeatable: every #[Authorize] on a Tool (own or inherited from a parent
 * class) must pass — they combine with AND, none is optional.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class Authorize
{
    public array $parameters;

    /**
     * @param  class-string<ToolAuthorizerInterface>  $authorizer
     */
    public function __construct(public string $authorizer, mixed ...$parameters)
    {
        $this->parameters = $parameters;
    }
}
