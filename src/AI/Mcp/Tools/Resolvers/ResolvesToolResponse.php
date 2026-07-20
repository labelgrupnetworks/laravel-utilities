<?php

namespace Labelgrup\LaravelUtilities\AI\Mcp\Tools\Resolvers;

use Illuminate\Http\JsonResponse;
use Illuminate\JsonSchema\JsonSchema as JsonSchemaFactory;
use Illuminate\Validation\UnauthorizedException;
use Labelgrup\LaravelUtilities\AI\Mcp\Tools\Errors\DefaultToolErrorResolver;
use Labelgrup\LaravelUtilities\AI\Mcp\Tools\Interfaces\McpScopeAuthorizerInterface;
use Labelgrup\LaravelUtilities\AI\Mcp\Tools\Interfaces\ToolErrorResolverInterface;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use ReflectionClass;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response as HttpFoundationResponse;
use Throwable;

trait ResolvesToolResponse
{
    public function responseFromJsonResponse(JsonResponse $response): Response|ResponseFactory
    {
        $data = (array) $response->getData(true);
        $status_code = $response->getStatusCode();

        if ($status_code >= HttpFoundationResponse::HTTP_INTERNAL_SERVER_ERROR) {
            return Response::error('Tool has internal server error');
        }

        if ($status_code >= HttpFoundationResponse::HTTP_BAD_REQUEST) {
            return Response::error($this->sanitizeMessage($data['error'] ?? $data['message'] ?? HttpFoundationResponse::$statusTexts[$status_code]));
        }

        if (!$this->hasOutputSchema() && ($status_code === HttpFoundationResponse::HTTP_NO_CONTENT || $data === [])) {
            return Response::text('Success');
        }

        if (array_keys($data) === ['message']) {
            return Response::text($data['message']);
        }

        if (array_key_exists('data', $data) && is_null($data['data'])) {
            return Response::text('Success');
        }

        if (array_key_exists('message', $data) && array_key_exists('data', $data)) {
            $data = ['data' => $data['data']];
        }

        return Response::structured($this->transformResponse($data));
    }

    /**
     * Strip internal "[file … line …]" annotations so paths are never exposed to the client.
     */
    public function sanitizeMessage(string $message): string
    {
        return trim((string) preg_replace('/\s*\[file\s.+\sline\s\d+\]/', '', $message));
    }

    /**
     * laravel/mcp hook (Primitive::eligibleForRegistration()): hides write tools from
     * tools/list when the current MCP token only carries the ".read" scope, instead of
     * only rejecting the call at execution time via authorizeScope().
     */
    public function shouldRegister(): bool
    {
        return $this->isReadOnly() || $this->canWrite();
    }

    /**
     * Throws when the tool performs a write and the current MCP token only carries the
     * ".read" scope. Tokens without MCP scopes (non-MCP context) are left unrestricted.
     */
    protected function authorizeScope(): void
    {
        if (!$this->isReadOnly() && !$this->canWrite()) {
            throw new UnauthorizedException('insufficient_scope: this tool requires the mcp write scope');
        }
    }

    /**
     * Resolves via the configurable `laravel-utilities.mcp.scope_authorizer` class. A
     * consuming project has no authorizer to plug in by default, so writes are unrestricted.
     */
    private function canWrite(): bool
    {
        if (!$authorizer_class = config('laravel-utilities.mcp.scope_authorizer')) {
            return true;
        }

        if (!is_a($authorizer_class, McpScopeAuthorizerInterface::class, true)) {
            throw new RuntimeException("laravel-utilities.mcp.scope_authorizer debe implementar McpScopeAuthorizerInterface: {$authorizer_class}");
        }

        return app($authorizer_class)->canWrite();
    }

    /**
     * Map an exception to an MCP error via the configurable resolver (default:
     * DefaultToolErrorResolver). A project consuming this as a package can swap
     * `laravel-utilities.mcp.errors.resolver` for its own class instead of forking this trait.
     */
    protected function errorResponse(Throwable $exception): Response|ResponseFactory
    {
        $resolver_class = config('laravel-utilities.mcp.errors.resolver', DefaultToolErrorResolver::class);

        if (!is_a($resolver_class, ToolErrorResolverInterface::class, true)) {
            throw new RuntimeException("laravel-utilities.mcp.errors.resolver debe implementar ToolErrorResolverInterface: {$resolver_class}");
        }

        return app($resolver_class)->resolve($exception, $this);
    }

    /**
     * A tool with an output schema must always return structuredContent, even when the
     * underlying payload is an empty array (e.g. a list endpoint with zero results) —
     * MCP clients validate that shape and reject a bare text "Success" fallback.
     */
    protected function hasOutputSchema(): bool
    {
        return isset(JsonSchemaFactory::object($this->outputSchema(...))->toArray()['properties']);
    }

    protected function isReadOnly(): bool
    {
        return (new ReflectionClass($this))->getAttributes(IsReadOnly::class) !== [];
    }

    protected function respond(callable $resolve): Response|ResponseFactory
    {
        try {
            $this->authorizeScope();

            $response = $resolve();

            return match (true) {
                is_string($response) => Response::text($response),
                is_array($response) => Response::structured($this->transformResponse($response)),
                $response instanceof JsonResponse => $this->responseFromJsonResponse($response),
                default => Response::text('Success'),
            };
        } catch (Throwable $exception) {
            report($exception);

            return $this->errorResponse($exception);
        }
    }

    /**
     * Optional hook: override in a tool to post-process the raw controller/use-case payload
     * (e.g. strip heavy fields, reshape the envelope) before it becomes content/structuredContent.
     * Identity by default — a tool that doesn't override this gets the raw payload untouched.
     */
    protected function transformResponse(array $data): array
    {
        return $data;
    }
}
