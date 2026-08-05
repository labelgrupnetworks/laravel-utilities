<?php

namespace Labelgrup\LaravelUtilities\AI\Mcp\Tools\Enums;

enum OrchestratorFailureType: string
{
    case VALIDATION = 'VALIDATION';
    case CONTROLLED = 'CONTROLLED';
    case SYSTEM = 'SYSTEM';

    public function description(): string
    {
        return match ($this) {
            self::VALIDATION => 'The request failed input validation before any business logic ran. Fix the offending fields against the tool schema and retry.',
            self::CONTROLLED => 'A known, expected failure that the tool detected and described explicitly. The error message already explains what to do next.',
            self::SYSTEM => 'An unexpected internal error, or an external system left in an unknown state. This is not a request-data problem, so correcting the input will not help; retrying later might.',
        };
    }
}
