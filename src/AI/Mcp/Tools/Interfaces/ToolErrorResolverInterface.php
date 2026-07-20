<?php

namespace Labelgrup\LaravelUtilities\AI\Mcp\Tools\Interfaces;

use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Throwable;

interface ToolErrorResolverInterface
{
    public function resolve(Throwable $exception, ToolErrorResponseBuilderInterface $tool): Response|ResponseFactory;
}
