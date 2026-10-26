<?php

namespace Tests\Feature;

use App\Http\Middleware\GuardApiHeaders;
use Illuminate\Http\Request;
use Tests\TestCase;

class ApiHeaderGuardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['api_security.allowed_hosts' => ['api.resqperation.test'],
            'api_security.allowed_origins' => ['https://resqperation.test'],
            'api_security.rate_limit_store' => 'array', 'api_security.requests_per_minute' => 600]);
    }

    private function check(array $headers = [], string $url = 'https://api.resqperation.test/api/v1/auth/me'): int
    {
        $request = Request::create($url);
        foreach ($headers as $key => $value) $request->headers->set($key, $value);
        return app(GuardApiHeaders::class)->handle($request, fn () => response()->json(['ok' => true]))->getStatusCode();
    }

    public function test_exact_origin_allowed_but_lookalikes_null_and_cross_site_are_rejected(): void
    {
        $this->assertSame(200, $this->check(['Origin' => 'https://resqperation.test']));
        foreach (['https://resqperation.test.evil.test', 'https://evil.test', 'null', 'https://resqperation.test/evil', 'https://evil.test@resqperation.test'] as $origin) {
            $this->assertSame(403, $this->check(['Origin' => $origin]));
        }
        $this->assertSame(403, $this->check(['Sec-Fetch-Site' => 'cross-site']));
        $this->assertSame(403, $this->check(['Origin' => 'https://evil.test', 'Authorization' => 'Bearer forged']));
    }

    public function test_native_and_server_requests_are_not_mistaken_for_cross_origin_browsers(): void
    {
        $this->assertSame(200, $this->check(['Authorization' => 'Bearer route-must-still-validate-this']));
        $this->assertSame(200, $this->check(['Referer' => 'https://resqperation.test/dashboard']));
        $this->assertSame(403, $this->check(['Referer' => 'https://evil.test/dashboard']));
        $this->assertSame(403, $this->check([], 'https://evil.test/api/v1/auth/me'));
    }

    public function test_rate_limit_and_body_checks_run_before_database_or_controller(): void
    {
        config(['api_security.requests_per_minute' => 1]);
        $this->assertSame(200, $this->check());
        $this->assertSame(429, $this->check());
    }

    public function test_disallowed_content_type_does_not_reach_controller(): void
    {
        $request = Request::create('https://api.resqperation.test/api/v1/auth/login', 'POST', [], [], [], ['CONTENT_TYPE' => 'text/plain'], 'malformed');
        $this->assertSame(415, app(GuardApiHeaders::class)->handle($request, fn () => response('unexpected'))->getStatusCode());
    }
}
