<?php

namespace Labelgrup\LaravelUtilities\Core\UseCases;

use Symfony\Component\HttpFoundation\Response;

abstract class UseCase implements UseCaseInterface
{
    public int $success_status_code = Response::HTTP_OK;
    public string $response_message = 'Action has been finished';

    public function handle(): UseCaseResponse
    {
        try {
            $response = $this->action();

            return $this->success(__($this->response_message), $response, $this->success_status_code);
        } catch (\Throwable $exception) {
            $code = $exception->status ?? $exception->getCode();
            $code = array_key_exists($code, Response::$statusTexts) ? $code : Response::HTTP_INTERNAL_SERVER_ERROR;

            return $this->fail(
                __($exception->getMessage()),
                property_exists($exception, 'response') ? $exception->response : null,
                $code,
                property_exists($exception, 'errorCodeDescription') ? $exception->errorCodeDescription : null,
                $exception->getTrace() ?? []
            );
        }
    }

    protected function fail(
        ?string $message = null,
        mixed $data = null,
        int $code = Response::HTTP_BAD_REQUEST,
        ?string $error_code = null,
        array $trace = []
    ): UseCaseResponse {
        return new UseCaseResponse(
            false,
            $message,
            $code,
            $data,
            $error_code,
            $trace
        );
    }

    protected function success(
        ?string $message = null,
        mixed $data = null,
        int $code = Response::HTTP_OK
    ): UseCaseResponse {
        return new UseCaseResponse(
            true,
            $message,
            $code,
            $data
        );
    }
}
