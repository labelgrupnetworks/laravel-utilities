<?php

namespace Labelgrup\LaravelUtilities\Helpers;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\UnauthorizedException;
use Illuminate\Validation\ValidationException;
use Labelgrup\LaravelUtilities\Exceptions\CustomException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ExceptionHandler
{
    /**
     * Render the exception into an HTTP response to Laravel version 9.x 10.x
     * @throws \Throwable
     */
    public static function render(...$params)
    {
        if (version_compare(app()->version(), '11.0', '<')) {
            return self::renderForLegacyVersion(...$params);
        }

        self::renderCurrentVersion(...$params);
    }

    protected static function renderForLegacyVersion(\Throwable $exception, Request $request): JsonResponse
    {
        if ($exception instanceof CustomException) {
            return ApiResponse::fail(
                $exception->getMessage(),
                [],
                $exception->getCode(),
                $exception->error_code
            );
        }

        if ($exception instanceof ValidationException) {
            return ApiResponse::fail($exception->getMessage(), $exception->errors(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($exception instanceof AuthenticationException) {
            return ApiResponse::fail($exception->getMessage(), [], Response::HTTP_UNAUTHORIZED);
        }

        if ($exception instanceof UnauthorizedException) {
            return ApiResponse::fail($exception->getMessage(), [], Response::HTTP_UNAUTHORIZED);
        }

        if ($exception instanceof NotFoundHttpException) {
            return ApiResponse::fail($exception->getMessage(), [], Response::HTTP_NOT_FOUND);
        }

        if ($exception instanceof HttpException) {
            return ApiResponse::fail($exception->getMessage(), [], $exception->getStatusCode(), null, $exception->getTrace());
        }

        if (config('app.debug')) {
            return ApiResponse::fail($exception->getMessage(), [
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR, null, $exception->getTrace());
        }

        if ($exception instanceof \Exception) {
            return ApiResponse::fail($exception->getMessage(), [], Response::HTTP_INTERNAL_SERVER_ERROR, null, $exception->getTrace());
        }

        return ApiResponse::fail('Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR, null, $exception->getTrace());
    }

    protected static function renderCurrentVersion(Exceptions $exceptions): void
    {
        $exceptions
            ->render(function (CustomException $exception, Request $request) {
                return self::renderForLegacyVersion($exception, $request);
            })
            ->render(function (ValidationException $exception, Request $request) {
                return self::renderForLegacyVersion($exception, $request);
            })
            ->render(function (AuthenticationException $exception, Request $request) {
                return self::renderForLegacyVersion($exception, $request);
            })
            ->render(function (UnauthorizedException $exception, Request $request) {
                return self::renderForLegacyVersion($exception, $request);
            })
            ->render(function (NotFoundHttpException $exception, Request $request) {
                return self::renderForLegacyVersion($exception, $request);
            })
            ->render(function (HttpException $exception, Request $request) {
                return self::renderForLegacyVersion($exception, $request);
            })
            ->render(function (\Exception $exception, Request $request) {
                return self::renderForLegacyVersion($exception, $request);
            })
            ->render(function (\Throwable $exception, Request $request) {
                return self::renderForLegacyVersion($exception, $request);
            });
    }
}
