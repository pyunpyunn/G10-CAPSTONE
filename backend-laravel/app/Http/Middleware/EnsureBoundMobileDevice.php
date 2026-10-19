<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnsureBoundMobileDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();
        $tokenName = is_string($token?->name ?? null) ? $token->name : '';

        if (! $user || ! str_starts_with($tokenName, 'mobile-device:')) {
            return response()->json(['message' => 'A device-bound mobile token is required.'], 401);
        }

        $deviceUuid = substr($tokenName, strlen('mobile-device:'));
        if ($deviceUuid === '' || ! hash_equals($deviceUuid, (string) $request->header('X-Device-UUID'))) {
            return response()->json(['message' => 'The device identifier does not match this access token.'], 403);
        }

        $device = DB::table('devices')->where('device_uuid', $deviceUuid)->first();
        if (! $device || (string) $device->user_id !== (string) $user->user_id) {
            return response()->json(['message' => 'This mobile device is not registered to the authenticated account.'], 401);
        }

        $responder = DB::table('responders')->where('user_id', $user->user_id)->first(['team_id']);
        if (! $responder || (int) $responder->team_id !== (int) $device->team_id || ! $token->can('team:'.(int) $device->team_id)) {
            return response()->json(['message' => 'This token is no longer bound to the rescuer\'s current team. Sign in again.'], 403);
        }

        $request->attributes->set('mobile_device', $device);

        return $next($request);
    }
}