<?php

namespace Labelgrup\LaravelUtilities\Core\UseCases;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Labelgrup\LaravelUtilities\Helpers\ApiResponse;
use Symfony\Component\HttpFoundation\Response;

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
        mixed $data = null
	)
	{
		$this->success = $success;
		$this->message = $message;
		$this->code = $code;
		$this->data = $data;
	}

	public function isSuccess(): bool
	{
		return $this->success;
	}

	public function getMessage(): ?string
	{
		return $this->message;
	}

	public function getCode(): int
	{
		return $this->code;
	}

	public function getData(): mixed
	{
		return $this->data;
	}

	public function toArray(): array
	{
		return [
			'success' => $this->success,
			'code' => $this->code,
			'message' => $this->message,
			'data' => $this->data
		];
	}

	public function responseToApi(bool $responseSimplified = false, ?string $resourceClass = null): \Illuminate\Http\JsonResponse
	{
		$this->code = array_key_exists($this->code, Response::$statusTexts) ? $this->code : Response::HTTP_INTERNAL_SERVER_ERROR;

		if (!$this->success) {
			return ApiResponse::fail(
				$this->message,
				is_array($this->data)
					? $this->data
					: ['errors' => $this->data],
				$this->code
			);
		}

        if ($responseSimplified) {
            return ApiResponse::ok($this->parseResponse($resourceClass), $this->code);
		}

		return ApiResponse::done($this->message, $this->parseResponse($resourceClass), $this->code);
	}

	private function parseResponse(?string $resourceClass = null): mixed
	{
		$response = $this->data;

		if ($resourceClass) {
			try {
				if ($this->data instanceof Collection) {
					$response = $resourceClass::collection($this->data);
				} else if ($this->data instanceof Model) {
					$response = $resourceClass::make($this->data);
				} else if ($this->data instanceof LengthAwarePaginator) {
					$response = ApiResponse::parsePagination($this->data, $resourceClass);
				}
			} catch (\Throwable $_) {}
		}

		return is_array($response) || is_object($response) ? $response : ['data' => $response];
	}
}
