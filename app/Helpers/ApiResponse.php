<?php

namespace App\Helpers;

use App\Http\Resources\ApiCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ApiResponse
{
    public static function success(
        string $message = 'OK',
        mixed $data = null,
        int $code = Response::HTTP_OK
    ): JsonResponse {
        $response = [
            'status'  => 'success',
            'message' => $message,
        ];

        if (!is_null($data)) {
            if ($data instanceof ApiCollection) {
                $resolved  = $data->toArray(request());
                $response  = array_merge($response, $resolved);
            } else {
                $response['data'] = $data;
            }
        }

        return response()->json($response, $code);
    }

    public static function error(
        string $message = 'Error',
        int $code = Response::HTTP_BAD_REQUEST,
        mixed $errors = null
    ): JsonResponse {
        $response = [
            'status'  => 'error',
            'message' => $message,
        ];

        if (!is_null($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }
}