<?php

namespace Labelgrup\LaravelUtilities\Core\UseCases;

use Labelgrup\LaravelUtilities\Helpers\ApiResponse;

class UseCaseResponse
{
    protected bool $success;
    protected ?string $message;
    protected int $code;
    protected mixed $data = null;

    public function __construct(
        bool $success,
        ?string $message,
        int $code,
        $data = null
    )
    {
        $this->success = $success;
        $this->message = $message;
        $this->code = $code;
        $this->data = $data;
    }

    public function isSuccess (): bool
    {
        return $this->success;
    }

    public function getMessage (): ?string
    {
        return $this->message;
    }

    public function getCode (): int
    {
        return $this->code;
    }

    public function getData (): mixed
    {
        return $this->data;
    }

    public function toArray (): array
    {
        return [
            'success' => $this->success,
            'code' => $this->code,
            'message' => $this->message,
            'data' => $this->data
        ];
    }

    public function responseToApi (): \Illuminate\Http\JsonResponse
    {
        if ( !$this->success ) {
            return ApiResponse::fail(
                $this->message,
                is_array($this->data)
                    ? $this->data
                    : ['errors' => $this->data],
                $this->code
            );
        }

        return ApiResponse::done(
            $this->message,
            is_array($this->data)
                ? $this->data
                : ['data' => $this->data],
            $this->code
        );
    }
}
