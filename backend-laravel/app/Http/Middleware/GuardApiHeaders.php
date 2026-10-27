<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class GuardApiHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // Runs before operational DB access. File/Redis cache, never the SQL DB.
        $limiter = new RateLimiter(Cache::store(config('api_security.rate_limit_store', 'file')));
        $key = 'api-edge:'.hash('sha256', (string) $request->ip());
        $limit = config('api_security.requests_per_minute', 6000);
        // A clock change or stale cache must not turn a one-minute limit into
        // a prolonged outage for every client behind the same API proxy.
        if ($limiter->availableIn($key) > 60) {
            $limiter->clear($key);
        }
        if ($limiter->tooManyAttempts($key, $limit)) {
            return response()->json(['message' => 'Too many API requests. Try again shortly.'], 429)
                ->header('Retry-After', $limiter->availableIn($key));
        }
        $limiter->hit($key, 60);

        if (! in_array(strtolower($request->getHost()), config('api_security.allowed_hosts', []), true)) {
            return response()->json(['message' => 'This API host is not allowed.'], 403);
        }
        $origin = $request->header('Origin');
        $referer = $request->header('Referer');
        if ($origin !== null) {
            if (! in_array($this->origin($origin, true), config('api_security.allowed_origins', []), true)) {
                return response()->json(['message' => 'This browser origin is not allowed.'], 403);
            }
        } elseif ($referer !== null) {
            if (! in_array($this->origin($referer), config('api_security.allowed_origins', []), true)) {
                return response()->json(['message' => 'This browser origin is not allowed.'], 403);
            }
        } elseif ($request->header('Sec-Fetch-Site') === 'cross-site') {
            return response()->json(['message' => 'Cross-site API requests require an approved origin.'], 403);
        }
        // Native mobile, signed downloads, and server integrations have no
        // browser Origin. Their route-level bearer/device/key/signature checks
        // remain mandatory; no public header acts as an authentication secret.
        if ((int) $request->header('Content-Length', 0) > config('api_security.max_body_bytes')) {
            return response()->json(['message' => 'The request body is too large.'], 413);
        }
        if (in_array($request->method(), ['POST','PUT','PATCH'], true) && $request->getContent() !== '') {
            $type = strtolower(explode(';', (string) $request->header('Content-Type'))[0]);
            if (! in_array($type, ['application/json','multipart/form-data','application/x-www-form-urlencoded'], true)) {
                return response()->json(['message' => 'Unsupported API content type.'], 415);
            }
        }
        return $next($request);
    }

    private function origin(string $value, bool $strict = false): ?string
    {
        $url = parse_url($value);
        if (!is_array($url) || !in_array($url['scheme'] ?? '', ['http','https'], true)
            || empty($url['host']) || isset($url['user']) || isset($url['pass'])
            || ($strict && (!empty($url['query']) || !empty($url['fragment']) || !in_array($url['path'] ?? '', ['', '/'], true)))) return null;
        $scheme = strtolower($url['scheme']);
        $port = $url['port'] ?? null;
        return $scheme.'://'.strtolower($url['host']).($port && !(($scheme === 'https' && $port === 443) || ($scheme === 'http' && $port === 80)) ? ':'.$port : '');
    }
}
