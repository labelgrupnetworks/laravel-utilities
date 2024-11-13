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
        public ?array $report_data = ['logs' => []],
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
        $request = request();
        $method = $request->method();
        $requestParams = static function () use ($method, $request) {
            return $method === 'POST' || $method === 'PUT'
                ? $request->all()
                : $request->input();
        };
        $this->data = [
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
            'error' => $this->error_code,
            'user_id' => auth()->user()->id ?? null,
            'user_email' => auth()->user()->email ?? null,
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'request' => $requestParams(),
            'response' => $this->response
        ];

        foreach ($this->report_data as $key => $value) {
            $this->reportNow($key, $value);
        }
    }

    public function reportNow(string $channel, $value): void
    {
        match ($channel) {
            'logs' => $this->reportLogs()
        };
    }

    public function reportLogs(): void
    {
        $message = $this->error_code . ':' . $this->getMessage();
        Log::warning($message, $this->data);
    }
}
