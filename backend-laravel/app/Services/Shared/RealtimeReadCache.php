<?php

namespace App\Services\Shared;

use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Predis\Connection\ConnectionException;

class RealtimeReadCache
{
    public function remember(string $name, Closure $read): mixed
    {
        if (! config('realtime.read_cache')) {
            return $read();
        }
        $cache = Cache::store(config('realtime.cache_store'));
        try {
            $revision = $cache->get($this->revisionKey(), 'initial');
            $key = $this->revisionKey().':'.$revision.':'.$name;
            $cached = $cache->get($key);
            if ($cached !== null) {
                return $cached;
            }

            // One producer per shared snapshot prevents cache-miss stampedes.
            return $cache->lock($key.':lock', 10)->block(2,
                fn () => $cache->remember($key, 2, $read));
        } catch (ConnectionException|LockTimeoutException) {
            // Reads remain available when Redis is down; report persistence is independent.
            return $read();
        }
    }

    public function invalidate(string $batchId): void
    {
        if (config('realtime.read_cache')) {
            Cache::store(config('realtime.cache_store'))->put($this->revisionKey(), $batchId, 86400);
        }
    }

    private function revisionKey(): string
    {
        return 'realtime:'.config('app.env').':'.config('realtime.connection').':revision';
    }
}
