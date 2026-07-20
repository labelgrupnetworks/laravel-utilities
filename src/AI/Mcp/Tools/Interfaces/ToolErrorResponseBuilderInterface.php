<?php

namespace Labelgrup\LaravelUtilities\AI\Mcp\Tools\Interfaces;

use Illuminate\Http\JsonResponse;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

interface ToolErrorResponseBuilderInterface
{
    public function responseFromJsonResponse(JsonResponse $response): Response|ResponseFactory;

    public function sanitizeMessage(string $message): string;
}
