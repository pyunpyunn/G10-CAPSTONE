<?php

namespace App\Http\Middleware;

use App\Services\Mobile\HouseholdAccessService;
use Closure;
use Illuminate\Http\Request;

class RequireHouseholdDeviceOwner
{
    public function handle(Request $request, Closure $next)
    {
        if (HouseholdAccessService::pending($request->user()) && ! in_array($request->path(), [
            'api/v1/auth/me', 'api/v1/auth/logout', 'api/v1/household/device-owner/options', 'api/v1/household/device-owner/select',
        ], true)) {
            return response()->json(['message' => 'Choose who is using this phone first.', 'code' => 'DEVICE_OWNER_REQUIRED'], 403);
        }
        return $next($request);
    }
}
