<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponseTrait
{
    public function successResponse($data = null, string $message = 'Success', int $status = 200): JsonResponse
    {
        return response()->json([
            'status'  => 'success',
            'message' => $message,
            'data'    => $data,
        ], $status);
    }

    public function errorResponse(string $message = 'Error', array $errors = [], int $status = 500): JsonResponse
    {
        return response()->json([
            'status'  => 'error',
            'message' => $message,
            'errors'  => $errors ?: null,
        ], $status);
    }
}
