<?php
// app/Http/Responses/ApiResponse.php
namespace App\Http\Responses;

use Illuminate\Http\Resources\Json\JsonResource;

class ApiResponse
{
    public static function beforeResponse(): void {
        // Perform any actions needed before the response is sent
    }

    public static function model(JsonResource $resource, array $meta = [])
    {
        static::beforeResponse();

        return response()->json([
            'data' => $resource,
            'meta' => $meta,
        ]);
    }

    public static function success(string $message, array $meta = [])
    {
        static::beforeResponse();

        return response()->json([
            'message' => $message,
            'meta' => $meta,
        ]);
    }

    public static function error(string $message, int $status = 400, array $meta = [])
    {
        static::beforeResponse();

        return response()->json([
            'error' => $message,
            'meta' => $meta,
        ], $status);
    }
}
