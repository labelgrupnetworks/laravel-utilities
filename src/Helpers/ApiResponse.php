<?php

namespace Labelgrup\LaravelUtilities\Helpers;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ApiResponse
{
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

    public static function response (
        array $data,
        int $code
    ): JsonResponse
    {
        return response()->json($data, $code);
    }
}
