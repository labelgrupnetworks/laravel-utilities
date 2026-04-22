<?php

namespace Labelgrup\LaravelUtilities\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class CustomException extends \Exception
{
    protected array $data = [];

    public function __construct(
        public string $error_code,
        public string $error_message,
        public int $http_code = Response::HTTP_INTERNAL_SERVER_ERROR,
        public ?array $report_data = [],
        public ?array $response = [],
        public bool $should_render = true
    ) {
        parent::__construct($error_message, $http_code);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'http_code' => $this->getCode(),
            'system_error' => $this->error_code
        ], $this->getCode());
    }

    public function report(): void
    {
        $message = $this->getMessage();
        $data = $this->getData();

        if ($this->getCode() >= Response::HTTP_INTERNAL_SERVER_ERROR) {
            Log::error($message, $data);

            return;
        }

        if ($this->getCode() >= Response::HTTP_OK && $this->getCode() < Response::HTTP_MULTIPLE_CHOICES) {
            Log::debug($message, $data);

            return;
        }

        Log::warning($message, $data);
    }

    protected function getData(): array
    {
        return [
            'exception' => [
                'class' => get_class($this),
                'message' => $this->getMessage(),
                'code' => $this->http_code,
                'file' => $this->getFile(),
                'line' => $this->getLine(),
                'trace' => $this->getTrace(),
                'error_code' => $this->error_code,
                'extra_data' => $this->report_data
            ]
        ];
    }
}
