<?php

namespace Labelgrup\LaravelUtilities\Helpers;

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpFoundation\Response;

class ApiResponse
{
    /**
     * Response success with data
     *
     * @param null|array|object $data
     * @param int $code
     * @return JsonResponse
     */
    public static function ok(
        null|array|object $data,
        int $code = Response::HTTP_OK
    ): JsonResponse {
        return self::response($data ?? [], $code);
    }

    /**
     * Response success with message and data
     *
     * @param string $message
     * @param array|object|null $data
     * @param int $code
     * @return JsonResponse
     */
    public static function done(
        string $message,
        null|array|object $data = [],
        int $code = Response::HTTP_OK
    ): JsonResponse {
        $responseData = [
            'message' => $message
        ];

        if (!is_null($data) && !is_array($data)) {
            $data = (array)$data;
        }

        if (!is_null($data) && count($data)) {
            $responseData['result'] = $data;
        }

        return self::response($responseData, $code);
    }

    /**
     * Response error with errors
     *
     * @param array|object $errors
     * @param int $code
     * @return JsonResponse
     */
    public static function error(
        array|object $errors,
        int $code = Response::HTTP_BAD_REQUEST
    ): JsonResponse {
        return self::response($errors, $code);
    }

    /**
     * Response error with message and errors
     *
     * @param string $message
     * @param array|object $errors
     * @param int $code
     * @param array $trace
     * @return JsonResponse
     */
    public static function fail(
        string $message,
        array|object $errors = [],
        int $code = Response::HTTP_BAD_REQUEST,
        array $trace = []
    ): JsonResponse {
        $responseData = [
            'message' => $message
        ];

        if (!is_array($errors)) {
            $errors = (array)$errors;
        }

        if (count($errors)) {
            $responseData['errors'] = $errors;
        }

        if (config('app.debug')) {
            $responseData['trace'] = $trace;
        }

        return self::response($responseData, $code);
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
     * @return JsonResponse
     */
    public static function response(
        array|object $data,
        int $code
    ): JsonResponse {
        return response()->json($data, $code);
    }
}
