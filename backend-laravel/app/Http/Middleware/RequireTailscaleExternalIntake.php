<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

class RequireTailscaleExternalIntake
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('api/v1/external/*') && config('external_intake.tailscale_only')) {
            // Use the socket peer; forwarded headers must not bypass network restrictions.
            $peer = (string) $request->server('REMOTE_ADDR', '');
            if (! IpUtils::checkIp($peer, ['100.64.0.0/10', 'fd7a:115c:a1e0::/48'])) {
                return response()->json(['message' => 'External integrations require a Tailscale connection.'], 403);
            }
        }

        return $next($request);
    }
}
