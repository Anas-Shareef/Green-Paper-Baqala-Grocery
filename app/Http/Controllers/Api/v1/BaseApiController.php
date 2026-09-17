<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

class BaseApiController extends Controller
{
    /**
     * Standardized Success Response Envelope
     */
    protected function successResponse(mixed $data = null, string $message = 'Success', int $code = 200, mixed $meta = null): JsonResponse
    {
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];

        if (is_array($data)) {
            foreach ($data as $key => $val) {
                if (!isset($response[$key])) {
                    $response[$key] = $val;
                }
            }
        }

        if ($meta !== null) {
            $response['meta'] = $meta;
        }

        return response()->json($response, $code);
    }

    /**
     * Standardized Error Response Envelope
     */
    protected function errorResponse(string $message = 'An error occurred', mixed $errors = [], int $code = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $code);
    }

    /**
     * Standardized Paginated Collection Response Envelope
     */
    protected function paginatedResponse(LengthAwarePaginator $paginator, string $message = 'Data retrieved successfully'): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'has_more' => $paginator->hasMorePages(),
            ],
        ], 200);
    }
}
