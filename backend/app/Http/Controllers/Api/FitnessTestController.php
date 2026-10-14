<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;

class FitnessTestController
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'service' => 'fitnessbooking-api',
            'status' => 'ok',
            'message' => 'Backend erreichbar',
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
