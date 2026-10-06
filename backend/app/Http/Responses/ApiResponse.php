<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * The single API envelope: { success, message, data, errors } (spec 3.6).
 */
class ApiResponse
{
    public static function success(mixed $data = null, string $message = 'OK', int $status = 200): JsonResponse
    {
        if ($data instanceof JsonResource || $data instanceof ResourceCollection) {
            $data = $data->resolve(request());
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'errors' => null,
        ], $status);
    }

    /**
     * Paginated list: data = { items: [...], meta: {...}, ...extra }.
     *
     * @param  class-string<JsonResource>  $resource
     */
    public static function paginated(\Illuminate\Contracts\Pagination\LengthAwarePaginator $page, string $resource, array $extra = [], string $message = 'OK'): JsonResponse
    {
        return self::success([
            'items' => $resource::collection($page->getCollection())->resolve(request()),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ] + $extra, $message);
    }

    /** @param array<string, mixed>|null $errors */
    public static function error(string $message, int $status = 400, ?array $errors = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => null,
            'errors' => $errors,
        ], $status);
    }
}
