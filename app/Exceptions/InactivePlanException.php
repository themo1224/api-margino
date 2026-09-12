<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;

class InactivePlanException extends Exception implements ShouldntReport
{
    public function __construct()
    {
        parent::__construct('The shop or plan is not allowed to use the connector.');
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'plan_inactive',
                'message' => $this->getMessage(),
            ],
        ], 403);
    }
}
