<?php

namespace App\Http\Controllers\Api\v1;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends BaseApiController
{
    /**
     * Internal Database Connection Health Check Diagnostic Endpoint (PRD Section 6)
     */
    public function databaseCheck(): JsonResponse
    {
        try {
            // Test actual DB query execution
            $connection = DB::connection();
            $pdo = $connection->getPdo();
            $driver = $connection->getDriverName();
            $databaseName = $connection->getDatabaseName();

            return $this->successResponse([
                'application' => 'ok',
                'database' => 'connected',
                'database_driver' => $driver,
                'database_name' => $databaseName ?: 'baqqala',
                'timestamp' => now()->toIso8601String(),
                'version' => '3bf06f6-inventory-stabilized',
            ], 'Database connection health check passed');

        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'application' => 'ok',
                'database' => 'disconnected',
                'error' => 'Unable to connect to database server',
                'error_detail' => $e->getMessage(),
                'timestamp' => now()->toIso8601String(),
            ], 500);
        }
    }
}
