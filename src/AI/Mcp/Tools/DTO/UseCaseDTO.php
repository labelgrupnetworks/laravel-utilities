<?php

namespace Labelgrup\LaravelUtilities\AI\Mcp\Tools\DTO;

use Labelgrup\LaravelUtilities\Core\UseCases\UseCase;

class UseCaseDTO
{
    public function __construct(
        public UseCase $use_case,
        public bool $response_simplified = false,
        public ?string $resource_class = null
    ) {
    }
}
