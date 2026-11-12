<?php

namespace Tests\Feature;

use App\Http\Middleware\RequireTailscaleExternalIntake;
use Illuminate\Http\Request;
use Tests\TestCase;

class TailscaleExternalIntakeTest extends TestCase
{
    public function test_external_endpoints_require_a_tailscale_socket_peer(): void
    {
        config(['external_intake.tailscale_only' => true]);
        foreach (['reports/handshake', 'report-requests', 'resource-requests', 'reports/download/test.pdf'] as $path) {
            foreach (['100.90.69.23' => 200, 'fd7a:115c:a1e0::1' => 200, '192.168.1.2' => 403, '127.0.0.1' => 403, '8.8.8.8' => 403] as $peer => $expected) {
                $request = Request::create('/api/v1/external/'.$path, 'POST', [], [], [], [
                    'REMOTE_ADDR' => $peer, 'HTTP_X_FORWARDED_FOR' => '100.90.69.23',
                ]);
                $response = (new RequireTailscaleExternalIntake)->handle($request, fn () => response()->json(['ok' => true]));
                $this->assertSame($expected, $response->getStatusCode(), $path.' '.$peer);
            }
        }
    }

    public function test_web_and_mobile_routes_remain_accessible_outside_tailscale(): void
    {
        config(['external_intake.tailscale_only' => true]);
        foreach (['auth/login', 'mobile/login', 'reports/external/requests'] as $path) {
            $request = Request::create('/api/v1/'.$path, 'POST', [], [], [], ['REMOTE_ADDR' => '192.168.1.2']);
            $response = (new RequireTailscaleExternalIntake)->handle($request, fn () => response()->json(['ok' => true]));
            $this->assertSame(200, $response->getStatusCode());
        }
    }
}
