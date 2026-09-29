<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class HealthController extends Controller
{
    public function live(): JsonResponse
    {
        return response()->json(['status' => 'live']);
    }

    public function ready(Request $request): JsonResponse
    {
        $requestId = (string) Str::uuid();
        $connection = 'resq_local';

        try {
            DB::connection($connection)->select('SELECT 1');

            return response()->json(['status' => 'ready'])
                ->header('X-Request-ID', $requestId);
        } catch (Throwable $exception) {
            Log::warning('Operational database readiness check failed.', [
                'request_id' => $requestId,
                'route_name' => $request->route()?->getName() ?? $request->path(),
                'connection_name' => $connection,
                'exception_class' => $exception::class,
            ]);

            return response()->json([
                'status' => 'unavailable',
                'message' => 'Database unreachable.',
                'request_id' => $requestId,
            ], 503)->header('X-Request-ID', $requestId);
        }
    }
}
