<?php

namespace Labelgrup\LaravelUtilities\Helpers;

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedJsonResponse;

class ApiResponse
{
    /**
     * Response success with data
     *
     * @param null|array|object $data
     * @param int $code
     * @param bool $streamJson
     *
     * @return JsonResponse|StreamedJsonResponse
     */
    public static function ok(
        null|array|object $data,
        int $code = Response::HTTP_OK,
        bool $streamJson = false
    ): JsonResponse|StreamedJsonResponse {
        return self::response($data ?? [], $code, $streamJson);
    }

    /**
     * Response success with message and data
     *
     * @param string $message
     * @param array|object|null $data
     * @param int $code
     * @param bool $streamJson
     *
     * @return JsonResponse|StreamedJsonResponse
     */
    public static function done(
        string $message,
        null|array|object $data = [],
        int $code = Response::HTTP_OK,
        bool $streamJson = false
    ): JsonResponse|StreamedJsonResponse {
        $responseData = [
            'message' => $message
        ];

        if (!is_null($data) && !is_array($data)) {
            $data = (array)$data;
        }

        if (!is_null($data) && count($data)) {
            $responseData['result'] = $data;
        }

        return self::response($responseData, $code, $streamJson);
    }

    /**
     * Response error with errors
     *
     * @param array|object $errors
     * @param int $code
     * @param bool $streamJson
     *
     * @return JsonResponse|StreamedJsonResponse
     */
    public static function error(
        array|object $errors,
        int $code = Response::HTTP_BAD_REQUEST,
        bool $streamJson = false
    ): JsonResponse|StreamedJsonResponse {
        return self::response($errors, $code, $streamJson);
    }

    /**
     * Response error with message and errors
     *
     * @param string $message
     * @param array|object $errors
     * @param int $code
     * @param string|null $error_code
     * @param array $trace
     * @param bool $streamJson
     *
     * @return JsonResponse|StreamedJsonResponse
     */
    public static function fail(
        string $message,
        array|object $errors = [],
        int $code = Response::HTTP_BAD_REQUEST,
        ?string $error_code = null,
        array $trace = [],
        bool $streamJson = false
    ): JsonResponse|StreamedJsonResponse {
        $responseData = [
            'message' => $message
        ];

        if (!is_array($errors)) {
            $errors = (array)$errors;
        }

        if (count($errors)) {
            $responseData['errors'] = array_key_exists('errors', $errors) ? $errors['errors'] : $errors;
        }

        if ($error_code) {
            $responseData['error_code'] = $error_code;
        }

        if (!empty($trace) && config('app.debug')) {
            $responseData['trace'] = $trace;
        }

        ksort($responseData);

        return self::response($responseData, $code, $streamJson);
    }

    /**
     * Map pagination data with a resource
     *
     * @param LengthAwarePaginator $list
     * @param $instanceResource
     * @param ...$instanceParams
     * @return array
     */
    public static function parsePagination(
        LengthAwarePaginator $list,
        $instanceResource = null,
        ...$instanceParams
    ): array {
        return [
            'items' => collect($list->items())->map(function ($item) use ($instanceResource, $instanceParams) {
                if ($instanceResource) {
                    return new $instanceResource($item, ...$instanceParams);
                }

                return $item;
            }),
            'data' => [
                'current_page' => $list->currentPage(),
                'last_page' => $list->lastPage(),
                'total_items' => $list->total()
            ]
        ];
    }

    /**
     * Response json
     *
     * @param array|object $data
     * @param int $code
     * @param bool $streamJson
     *
     * @return JsonResponse|StreamedJsonResponse
     */
    public static function response(
        array|object $data,
        int $code,
        bool $streamJson = false
    ): JsonResponse|StreamedJsonResponse {
        return ($streamJson ? response()->streamJson($data, $code) : response()->json($data, $code));
    }
}
