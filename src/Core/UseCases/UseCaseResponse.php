<?php

namespace Labelgrup\LaravelUtilities\Core\UseCases;

use Labelgrup\LaravelUtilities\Helpers\ApiResponse;
use Symfony\Component\HttpFoundation\Response;

class UseCaseResponse
{
	protected bool $success;
	protected ?string $message;
	protected int $code;
	protected mixed $data = null;

	public function __construct(
		bool    $success,
		?string $message,
		int     $code,
				$data = null
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

	public function responseToApi(bool $responseSimplified = false): \Illuminate\Http\JsonResponse
	{
		$code = array_key_exists($this->code, Response::$statusTexts) ? $this->code : Response::HTTP_INTERNAL_SERVER_ERROR;

		if (!$this->success) {
			if ($responseSimplified) {
				return ApiResponse::error(is_array($this->data) ? $this->data : ['errors' => $this->data], $code);
			}

			return ApiResponse::fail(
				$this->message,
				is_array($this->data)
					? $this->data
					: ['errors' => $this->data],
				$code
			);
		}

		if ($responseSimplified) {
			return ApiResponse::ok(is_array($this->data) ? $this->data : ['data' => $this->data], $code);
		}

		return ApiResponse::done(
			$this->message,
			is_array($this->data)
				? $this->data
				: ['data' => $this->data],
			$code
		);
	}
}
