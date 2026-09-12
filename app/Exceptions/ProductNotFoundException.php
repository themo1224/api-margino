<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;

class ProductNotFoundException extends Exception implements ShouldntReport
{
    public function __construct()
    {
        parent::__construct('The product is not known to the API.');
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'product_not_found',
                'message' => $this->getMessage(),
            ],
        ], 404);
    }
}
