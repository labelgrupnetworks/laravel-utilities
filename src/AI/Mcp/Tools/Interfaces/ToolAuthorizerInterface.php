<?php

namespace Labelgrup\LaravelUtilities\AI\Mcp\Tools\Interfaces;

use Laravel\Mcp\Server\Tool;

interface ToolAuthorizerInterface
{
    /**
     * @throws \Throwable when the current context is not authorized to run the tool
     */
    public function authorize(Tool $tool, array $parameters): void;
}
