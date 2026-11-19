<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    /**
     * Return a standardized success JSON response.
     */
    public function success(mixed $data = null, string $message = 'Success', int $statusCode = 200, array $meta = []): JsonResponse
    {
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];

        if (!empty($meta)) {
            $response['meta'] = array_merge($meta, [
                'timestamp' => now()->toIso8601String(),
            ]);
        } else {
            $response['meta'] = [
                'timestamp' => now()->toIso8601String(),
            ];
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Return a standardized error JSON response.
     */
    public function error(string $message = 'Error', int $statusCode = 400, mixed $errors = null, ?string $code = null): JsonResponse
    {
        $response = [
            'success' => false,
            'code' => $code ?? $this->resolveErrorCode($statusCode),
            'message' => $message,
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        $response['timestamp'] = now()->toIso8601String();

        return response()->json($response, $statusCode);
    }

    /**
     * Resolve default string error code by HTTP status.
     */
    protected function resolveErrorCode(int $statusCode): string
    {
        return match ($statusCode) {
            400 => 'BAD_REQUEST',
            401 => 'UNAUTHENTICATED',
            403 => 'FORBIDDEN',
            404 => 'NOT_FOUND',
            405 => 'METHOD_NOT_ALLOWED',
            422 => 'VALIDATION_FAILED',
            429 => 'TOO_MANY_REQUESTS',
            500 => 'INTERNAL_SERVER_ERROR',
            default => 'ERROR',
        };
    }
}

