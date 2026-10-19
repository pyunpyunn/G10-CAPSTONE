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
        if (! app()->environment('testing') && config('app.schema_capability_cache_enabled', true)) {
            $connection = json_decode($key, true)[0] ?? config('database.default');
            $database = array_intersect_key((array) config('database.connections.'.$connection, []),
                array_flip(['driver', 'host', 'port', 'database', 'prefix']));
            $version = config('app.schema_capability_cache_version', '1');
            $cacheKey = 'schema_capability:v2:'.hash('sha256', json_encode([$connection, $database, $version, $key]));
            // The default cache may itself use the remote database.
            return Cache::store((string) config('app.schema_capability_cache_store', 'file'))
                ->remember($cacheKey, max(1, (int) config('app.schema_capability_cache_ttl', 300)), $lookup);
        }

        $request = app()->bound('request') ? app('request') : null;
        if (! $request instanceof Request) {
            return $lookup();
        }

        $cache = $request->attributes->get('schema_capability_cache', []);
        if (! array_key_exists($key, $cache)) {
            $cache[$key] = $lookup();
            $request->attributes->set('schema_capability_cache', $cache);
        }

        return $cache[$key];
    }
}
