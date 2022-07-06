<?php

namespace Labelgrup\LaravelUtilities\Helpers;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ApiResponse
{
    /**
     * @param string $message
     * @param array $data
     * @param int $code
     * @return JsonResponse
     */
    public static function done(
        string $message,
        array $data = [],
        int $code = Response::HTTP_OK
    ): JsonResponse
    {
        $responseData = [
            'message' => $message
        ];

        if ( count($data) ) {
            $responseData['result'] = $data;
        }

        return self::response($responseData, $code);
    }

    /**
     * @param string $message
     * @param array $errors
     * @param int $code
     * @return JsonResponse
     */
    public static function fail(
        string $message,
        array $errors = [],
        int $code = Response::HTTP_BAD_REQUEST
    ): JsonResponse
    {
        $responseData = [
            'message' => $message
        ];

        if ( count($errors) ) {
            $responseData['errors'] = $errors;
        }

        return self::response($responseData, $code);
    }

    /**
     * @param \Illuminate\Pagination\LengthAwarePaginator $list
     * @param $instanceResource
     * @param ...$instanceParams
     * @return array
     */
    public static function parsePagination(
        \Illuminate\Pagination\LengthAwarePaginator $list,
        $instanceResource = null,
        ...$instanceParams
    ): array
    {
        return [
            'items' => collect($list->items())->map(function ($item) use ($instanceResource, $instanceParams) {
                if ( $instanceResource ) {
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
     * @param array $data
     * @param int $code
     * @return JsonResponse
     */
    public static function response (
        array $data,
        int $code
    ): JsonResponse
    {
        return response()->json($data, $code);
    }
}
