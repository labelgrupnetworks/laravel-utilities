<?php

namespace Labelgrup\LaravelUtilities\AI\Mcp\Tools\Interfaces;

interface McpScopeAuthorizerInterface
{
    public function canWrite(): bool;
}
