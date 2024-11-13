<?php

namespace Labelgrup\LaravelUtilities\Helpers;

use Illuminate\Auth\AuthenticationException;
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
     * @throws \Throwable
     */
    public static function render(\Throwable $exception, Request $request): JsonResponse
    {
        if ($exception instanceof CustomException) {
            return ApiResponse::error([
                'code' => $exception->error_code,
                'error' => $exception->error_message,
            ], $exception->getCode());
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
            return ApiResponse::fail($exception->getMessage(), [], $exception->getStatusCode());
        }

        if (config('app.debug')) {
            return ApiResponse::fail($exception->getMessage(), [
                'request' => $request->all(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTrace(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        if ($exception instanceof \Exception) {
            return ApiResponse::fail($exception->getMessage(), [], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return ApiResponse::fail('Internal Server Error', [], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}
