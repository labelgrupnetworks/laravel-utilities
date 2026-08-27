<?php

namespace Labelgrup\LaravelUtilities\AI\Mcp\Tools\Abstracts;

use Illuminate\Validation\ValidationException;
use Labelgrup\LaravelUtilities\AI\Mcp\Tools\Enums\OrchestratorFailureStage;
use Labelgrup\LaravelUtilities\AI\Mcp\Tools\Enums\OrchestratorFailureType;
use Labelgrup\LaravelUtilities\AI\Mcp\Tools\Exceptions\OrchestratorToolException;
use Labelgrup\LaravelUtilities\AI\Mcp\Tools\Interfaces\ExternalResourceExceptionInterface;
use Labelgrup\LaravelUtilities\AI\Mcp\Tools\Interfaces\ToolErrorResponseBuilderInterface;
use Labelgrup\LaravelUtilities\AI\Mcp\Tools\Resolvers\ResolvesRequestClass;
use Labelgrup\LaravelUtilities\AI\Mcp\Tools\Resolvers\ResolvesToolName;
use Labelgrup\LaravelUtilities\AI\Mcp\Tools\Resolvers\ResolvesToolResponse;
use Labelgrup\LaravelUtilities\AI\Mcp\Tools\Resolvers\ResolvesToolSchemas;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Throwable;

/**
 * Fourth Tool type: orchestrates several UseCases in a single MCP call, splitting the
 * work into a side-effect-free prepare() and an execute() that may or may not mutate.
 * A failure's phase is reported via `_meta.failure_stage` so the caller knows whether
 * anything could have been written.
 *
 * Read-only tools still fit this shape: do the real work in prepare() and make execute()
 * a trivial `return Response::structured($prepared);`.
 */
abstract class OrchestratorTool extends Tool implements ToolErrorResponseBuilderInterface
{
    use NormalizesNullableSchemaTypes;
    use ResolvesRequestClass;
    use ResolvesToolName;
    use ResolvesToolResponse;
    use ResolvesToolSchemas;

    public function handle(Request $request): Response|ResponseFactory
    {
        $this->authorizeScope();

        try {
            $this->validateUsingRequestClass($request);

            $prepared = $this->prepare($request);
        } catch (ValidationException $exception) {
            return $this->failureFromValidation($exception, OrchestratorFailureStage::PREPARATION, OrchestratorFailureType::VALIDATION);
        } catch (OrchestratorToolException $exception) {
            return $this->failureFromException($exception);
        } catch (Throwable $exception) {
            return $this->failureDefault($exception, OrchestratorFailureStage::PREPARATION, OrchestratorFailureType::SYSTEM, $this->suggestedTool($exception));
        }

        try {
            return $this->execute($request, $prepared);
        } catch (OrchestratorToolException $exception) {
            return $this->failureFromException($exception);
        } catch (ExternalResourceExceptionInterface $exception) {
            return $this->failure(
                exception: $exception,
                error: $exception->getMessage(),
                stage: OrchestratorFailureStage::UNKNOWN,
                type: OrchestratorFailureType::SYSTEM,
                suggested_tool: $this->suggestedTool($exception)
            );
        } catch (Throwable $exception) {
            return $this->failure($exception, $exception->getMessage(), OrchestratorFailureStage::EXECUTE, OrchestratorFailureType::SYSTEM, $this->suggestedTool($exception));
        }
    }

    abstract protected function prepare(Request $request): mixed;

    abstract protected function execute(Request $request, mixed $prepared): Response|ResponseFactory;

    /**
     * Optional hook: override to point the caller at another Tool that would fix this
     * error (e.g. "product not found" -> `products_search`). Null by default.
     */
    protected function suggestedTool(Throwable $exception): ?string
    {
        return null;
    }

    private function failure(
        Throwable $exception,
        mixed $error,
        OrchestratorFailureStage $stage,
        OrchestratorFailureType $type,
        ?string $suggested_tool = null
    ): Response {
        report($exception);

        return Response::error(json_encode([
            'error' => $error,
            'failure_stage' => $stage->value,
            'failure_type' => $type->description(),
            'suggested_tool' => $suggested_tool
        ]));
    }

    private function failureDefault(
        Throwable $exception,
        OrchestratorFailureStage $stage,
        OrchestratorFailureType $type,
        ?string $suggested_tool = null
    ): Response {
        return $this->failure(
            exception: $exception,
            error: (string) $this->errorResponse($exception)->content(),
            stage: $stage,
            type: $type,
            suggested_tool: $suggested_tool
        );
    }

    private function failureFromException(OrchestratorToolException $exception): Response
    {
        return $this->failure(
            exception: $exception,
            error: $exception->getMessage(),
            stage: $exception->stage,
            type: OrchestratorFailureType::CONTROLLED,
            suggested_tool: $exception->suggested_tool
        );
    }

    private function failureFromValidation(
        ValidationException $exception,
        OrchestratorFailureStage $stage
    ): Response {
        return $this->failure(
            exception: $exception,
            error: $exception->errors(),
            stage: $stage,
            type: OrchestratorFailureType::VALIDATION
        );
    }
}
