<?php

namespace App\Http\Middleware;

use App\Support\RequestQueryProfile;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ProfileLocalQueries
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('app.query_profile_enabled') || ! app()->environment('local')) {
            return $next($request);
        }

        $profile = new RequestQueryProfile();
        $request->attributes->set('local_query_profile', $profile);
        $started = microtime(true);

        try {
            return $next($request);
        } finally {
            Log::info('Local API query profile', [
                'route' => $request->route()?->getName() ?? $request->path(),
                'query_count' => $profile->count,
                'db_ms' => round($profile->totalMs, 2),
                'response_ms' => round((microtime(true) - $started) * 1000, 2),
                'slowest' => $profile->slowest,
            ]);
        }
    }
}
