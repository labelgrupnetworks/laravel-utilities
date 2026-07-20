<?php

namespace Labelgrup\LaravelUtilities\AI\Mcp\Tools\Interfaces;

use Labelgrup\LaravelUtilities\AI\Mcp\Tools\DTO\UseCaseDTO;
use Laravel\Mcp\Request;

interface UseCaseToolInterface
{
    public function useCase(Request $request): UseCaseDTO;
}
