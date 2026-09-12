<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;

class InvalidApiKeyException extends Exception implements ShouldntReport
{
    public function __construct()
    {
        parent::__construct('The API key is missing or invalid.');
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'invalid_api_key',
                'message' => $this->getMessage(),
            ],
        ], 401);
    }
}
