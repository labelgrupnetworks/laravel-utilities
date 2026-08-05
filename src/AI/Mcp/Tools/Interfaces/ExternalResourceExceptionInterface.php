<?php

namespace Labelgrup\LaravelUtilities\AI\Mcp\Tools\Interfaces;

/**
 * Marker for a consumer-defined exception meaning "an external resource was called and
 * its outcome is unknown" (e.g. a timeout). OrchestratorTool::handle() catches it during
 * write() as OrchestratorFailureStage::UNKNOWN instead of WRITE, since the package cannot
 * reference any consumer's domain exception classes directly.
 */
interface ExternalResourceExceptionInterface
{
}
