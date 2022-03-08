<?php

namespace Labelgrup\LaravelUtilities\Core\UseCases;

use Symfony\Component\HttpFoundation\Response;

abstract class UseCase implements UseCaseInterface
{
    public function handle(): UseCaseResponse
    {
        try {
            $response = $this->action();
            return $this->success(__('Action has been finished'), $response);
        } catch ( \Throwable $exception ) {
            return $this->fail(__($exception->getMessage()), $exception->getTrace(), $exception->getCode());
        }
    }

    protected function success (
        ?string $message = null,
        mixed $data = null,
        int $code = Response::HTTP_OK
    ): UseCaseResponse
    {
        return new UseCaseResponse(
            true,
            $message,
            $code,
            $data
        );
    }

    protected function fail (
        ?string $message = null,
        mixed $data = null,
        int $code = Response::HTTP_BAD_REQUEST
    ): UseCaseResponse
    {
        return new UseCaseResponse(
            false,
            $message,
            $code,
            $data
        );
    }
}
