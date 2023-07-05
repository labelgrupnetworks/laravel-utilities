<?php

namespace Labelgrup\LaravelUtilities\Core\UseCases;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

abstract class UseCase implements UseCaseInterface
{
    public const SUCCESS_STATUS_CODE = Response::HTTP_OK;
	public string $response_message = 'Action has been finished';

    public function handle(): UseCaseResponse
    {
        try {
            $response = $this->action();
            return $this->success(__($this->response_message), $response, self::SUCCESS_STATUS_CODE);
        } catch ( \Throwable $exception ) {
            $code = array_key_exists($exception->getCode(), Response::$statusTexts)
                ? $exception->getCode()
                : Response::HTTP_INTERNAL_SERVER_ERROR;
            return $this->fail(__($exception->getMessage()), $exception->getTrace(), $code);
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
