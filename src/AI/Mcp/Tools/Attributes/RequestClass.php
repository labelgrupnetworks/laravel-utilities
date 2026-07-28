<?php

namespace Labelgrup\LaravelUtilities\AI\Mcp\Tools\Attributes;

use Attribute;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Declares which FormRequest's rules() validate this Tool's MCP arguments.
 * UseCaseTool::handle() runs it before useCase() and merges the validated
 * (and defaulted) data back into the Laravel\Mcp\Request.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class RequestClass
{
    /**
     * @param  class-string<FormRequest>  $class
     */
    public function __construct(public string $class)
    {
    }
}
