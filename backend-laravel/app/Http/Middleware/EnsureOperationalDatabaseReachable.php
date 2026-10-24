<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnsureOperationalDatabaseReachable
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('api/*') || $request->is('api/v1/health/live')) {
            return $next($request);
        }

        $connectionName = (string) config('database.default');
        $connection = config("database.connections.{$connectionName}", []);
        if (! in_array($connection['driver'] ?? null, ['mysql', 'mariadb'], true)) {
            return $next($request);
        }

        $url = $connection['url'] ?? null;
        $parsedUrl = is_string($url) && $url !== '' ? parse_url($url) : false;
        $host = (string) ($parsedUrl['host'] ?? $connection['host'] ?? '');
        $normalizedHost = trim($host, '[]');
        $port = (int) ($parsedUrl['port'] ?? $connection['port'] ?? 3306);
        if ($normalizedHost === '' || (! filter_var($normalizedHost, FILTER_VALIDATE_IP) && ! preg_match('/^[a-zA-Z0-9.-]+$/', $normalizedHost))) {
            return $next($request);
        }

        $timeout = min(3.0, max(1.0, (float) ($connection['options'][\PDO::ATTR_TIMEOUT] ?? 3)));
        $target = filter_var($normalizedHost, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)
            ? "tcp://[{$normalizedHost}]:{$port}"
            : "tcp://{$normalizedHost}:{$port}";
        $socket = @stream_socket_client($target, $errorCode, $errorMessage, $timeout);

        if ($socket !== false) {
            fclose($socket);

            return $next($request);
        }

        $requestId = (string) Str::uuid();
        Log::warning('Operational database connection preflight failed.', [
            'request_id' => $requestId,
            'route_name' => $request->route()?->getName() ?? $request->path(),
            'connection_name' => $connectionName,
            'host' => $host,
            'port' => $port,
            'errno' => $errorCode,
            'error' => $errorMessage,
        ]);

        return response()->json([
            'status' => 'unavailable',
            'message' => 'Database unreachable.',
            'request_id' => $requestId,
        ], 503)->header('X-Request-ID', $requestId);
    }
}
