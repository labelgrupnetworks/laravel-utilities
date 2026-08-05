<?php

namespace Labelgrup\LaravelUtilities\AI\Mcp\Tools\Enums;

enum OrchestratorFailureStage: string
{
    case PREPARATION = 'PREPARATION';
    case EXECUTE = 'EXECUTE';
    case UNKNOWN = 'UNKNOWN';
}
