<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class RealtimeHealthController extends Controller
{
    public function __invoke()
    {
        $db = DB::connection(config('realtime.connection'));
        $pending = $db->table('realtime_outbox')->whereNull('delivered_at');
        $oldest = (clone $pending)->min('created_at');
        $age = $oldest ? max(0, time() - strtotime($oldest)) : 0;
        $queueAvailable = true;
        if (config('realtime.queue_connection') === 'realtime_redis') {
            try {
                Redis::connection()->ping();
            } catch (\Throwable) {
                $queueAvailable = false;
            }
        }

        return response()->json([
            'durable' => config('realtime.durable'),
            'pending' => (clone $pending)->count(),
            'oldest_pending_seconds' => $age,
            'retrying' => (clone $pending)->where('attempts', '>', 1)->count(),
            'queue_available' => $queueAvailable,
        ], $queueAvailable && $age < 30 ? 200 : 503)->header('Cache-Control', 'no-store');
    }
}
