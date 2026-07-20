<?php

namespace Labelgrup\LaravelUtilities\AI\Mcp\Tools\Errors;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Labelgrup\LaravelUtilities\AI\Mcp\Tools\Interfaces\ToolErrorResolverInterface;
use Labelgrup\LaravelUtilities\AI\Mcp\Tools\Interfaces\ToolErrorResponseBuilderInterface;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Symfony\Component\HttpFoundation\Response as HttpFoundationResponse;
use Throwable;

class DefaultToolErrorResolver implements ToolErrorResolverInterface
{
    public function resolve(Throwable $exception, ToolErrorResponseBuilderInterface $tool): Response|ResponseFactory
    {
        $handled = $this->toJsonResponse($exception);

        if ($handled instanceof JsonResponse) {
            return $tool->responseFromJsonResponse($handled);
        }

        if (config('app.debug') === true) {
            return Response::error($tool->sanitizeMessage($exception->getMessage()));
        }

        return Response::error('Tool is temporarily unavailable');
    }

    protected function exposesMessage(Throwable $exception): bool
    {
        foreach (config('laravel-utilities.mcp.errors.exposed_exceptions', []) as $exception_class) {
            if ($exception instanceof $exception_class) {
                return true;
            }
        }

        return false;
    }

    protected function fromExceptionHandler(Throwable $exception): ?JsonResponse
    {
        $rendered = app(ExceptionHandler::class)->render(request(), $exception);

        return $rendered instanceof JsonResponse ? $rendered : null;
    }

    protected function fromHttpResponseException(HttpResponseException $exception): ?JsonResponse
    {
        $response = $exception->getResponse();

        if (!$response instanceof JsonResponse) {
            return null;
        }

        $data = (array) $response->getData(true);

        if ($response->getStatusCode() !== HttpFoundationResponse::HTTP_UNPROCESSABLE_ENTITY || empty($data['errors'])) {
            return $response;
        }

        return response()->json(
            ['error' => $this->formatValidationErrors($data['errors'])],
            HttpFoundationResponse::HTTP_UNPROCESSABLE_ENTITY
        );
    }

    /**
     * Shared by both raw ValidationException (DataObjectControllerTool/Spatie Data)
     * and HttpResponseException-wrapped validation (ControllerTool/ApiRequest::failedValidation())
     * so a field name is never lost regardless of which path produced the error.
     *
     * @param  array<string, array<string>>  $errors
     */
    protected function formatValidationErrors(array $errors): string
    {
        return collect($errors)
            ->map(fn (array $messages, string $field) => "{$field} failed validation: " . implode(', ', array_map(
                fn (string $message) => Str::startsWith($message, 'validation.') ? Str::after($message, 'validation.') : $message,
                $messages
            )))
            ->implode('; ');
    }

    /**
     * Normalizes every mapped exception shape into a JsonResponse so resolve() only
     * ever needs one call to responseFromJsonResponse() to build the final MCP Response.
     */
    protected function toJsonResponse(Throwable $exception): ?JsonResponse
    {
        if ($exception instanceof ValidationException) {
            return response()->json(
                ['error' => $this->formatValidationErrors($exception->errors())],
                HttpFoundationResponse::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        if ($exception instanceof HttpResponseException) {
            return $this->fromHttpResponseException($exception);
        }

        if ($this->exposesMessage($exception)) {
            return $this->fromExceptionHandler($exception);
        }

        return null;
    }
}
