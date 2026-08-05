<?php

namespace Labelgrup\LaravelUtilities\AI\Mcp\Tools\Exceptions;

use Labelgrup\LaravelUtilities\AI\Mcp\Tools\Enums\OrchestratorFailureStage;
use Throwable;

/**
 * Lets code inside prepare()/execute() declare its own failure_stage/suggested_tool
 * explicitly, overriding OrchestratorTool::handle()'s default phase-based inference.
 */
class OrchestratorToolException extends \Exception
{
    public function __construct(
        string $message,
        public OrchestratorFailureStage $stage,
        public ?string $suggested_tool = null,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }
}
