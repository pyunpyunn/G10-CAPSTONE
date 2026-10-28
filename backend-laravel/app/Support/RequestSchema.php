<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/** Cache schema metadata by connection and deployment version. */
class RequestSchema
{
    public static function hasTable(string $table): bool
    {
        return self::connection()->check('table', $table, null);
    }

    public static function hasColumn(string $table, string $column): bool
    {
        return self::connection()->check('column', $table, $column);
    }

    /** @return list<string> */
    public static function getColumnListing(string $table): array
    {
        return self::connection()->columns($table);
    }

    public static function connection(?string $name = null): RequestSchemaConnection
    {
        return new RequestSchemaConnection($name ?: (string) config('database.default'));
    }

    public static function remember(string $key, callable $lookup): mixed
    {
        $request = app()->bound('request') ? app('request') : null;
        $requestCache = $request instanceof Request
            ? $request->attributes->get('schema_capability_cache', [])
            : [];
        $ttl = max(1, (int) config('app.schema_capability_cache_ttl', 300));

        if (array_key_exists($key, $requestCache)) {
            $cached = $requestCache[$key];
            if (is_array($cached) && array_key_exists('value', $cached)) {
                if (! array_key_exists('expires_at', $cached) || $cached['expires_at'] > now()->getTimestamp()) {
                    return $cached['value'];
                }
            } elseif (! is_array($cached)) {
                return $cached;
            }
        }

        if (! app()->environment('testing') && config('app.schema_capability_cache_enabled', true)) {
            $connection = json_decode($key, true)[0] ?? config('database.default');
            $database = array_intersect_key((array) config('database.connections.'.$connection, []),
                array_flip(['driver', 'host', 'port', 'database', 'prefix']));
            $version = config('app.schema_capability_cache_version', '1');
            $cacheKey = 'schema_capability:v2:'.hash('sha256', json_encode([$connection, $database, $version, $key]));
            // The default cache may itself use the remote database.
            $value = Cache::store((string) config('app.schema_capability_cache_store', 'file'))
                ->remember($cacheKey, $ttl, $lookup);

            if ($request instanceof Request) {
                $requestCache = $request->attributes->get('schema_capability_cache', []);
                $requestCache[$key] = ['value' => $value, 'expires_at' => now()->addSeconds($ttl)->getTimestamp()];
                $request->attributes->set('schema_capability_cache', $requestCache);
            }

            return $value;
        }

        if (! $request instanceof Request) {
            return $lookup();
        }

        $value = $lookup();
        $requestCache = $request->attributes->get('schema_capability_cache', []);
        $requestCache[$key] = ['value' => $value];
        $request->attributes->set('schema_capability_cache', $requestCache);

        return $value;
    }
}
