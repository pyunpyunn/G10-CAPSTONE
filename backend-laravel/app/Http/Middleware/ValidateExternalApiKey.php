<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateExternalApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = config('reports.external_api_key');
        $provided = $request->header('X-API-KEY');
        if (!is_string($key) || $key === '' || !is_string($provided) || !hash_equals($key, $provided)) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized: Invalid or missing X-API-KEY header.'], 401);
        }
        return $next($request);
    }
}
