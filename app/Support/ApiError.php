<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

/**
 * Builds the API's consistent error envelope:
 * {"error": {"type": "...", "code": "...", "message": "..."}}
 */
class ApiError
{
    /**
     * @param  array<string, mixed>  $extra
     */
    public static function response(int $status, string $type, string $code, string $message, array $extra = []): JsonResponse
    {
        return response()->json(array_merge([
            'error' => [
                'type' => $type,
                'code' => $code,
                'message' => $message,
            ],
        ], $extra), $status);
    }
}
