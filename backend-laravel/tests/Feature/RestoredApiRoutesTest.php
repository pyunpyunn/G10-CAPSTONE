<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RestoredApiRoutesTest extends TestCase
{
    public function test_restored_routes_match_existing_controller_handlers(): void
    {
        $endpoints = [
            ['POST', 'external/reports/generate'],
            ['GET', 'external/reports/status/test-token'],
            ['GET', 'external/reports/download/test.pdf'],
            ['GET', 'health/live'],
            ['GET', 'health/ready'],
            ['POST', 'sms/inbound'],
            ['POST', 'auth/password-recovery/verify'],
            ['GET', 'realtime/health'],
            ['GET', 'global-search'],
            ['GET', 'headquarters-accounts'],
            ['POST', 'headquarters-accounts'],
            ['DELETE', 'rescuers/123'],
            ['GET', 'resource-request-types'],
            ['PATCH', 'resource-requests/123'],
            ['GET', 'archive/inquiry-logs'],
            ['POST', 'reports/generate'],
            ['GET', 'dispatches/communications'],
            ['GET', 'dispatches/welfare-checks'],
            ['GET', 'dispatches/member-check-queue'],
            ['GET', 'dispatches/priorities'],
            ['GET', 'dispatches/purok-priorities'],
            ['GET', 'dispatches/sitio-priorities'],
            ['GET', 'dispatches/criteria-timeline'],
            ['GET', 'dispatches/priority-settings'],
            ['PATCH', 'dispatches/priority-settings'],
            ['PUT', 'household/geotag'],
        ];

        foreach ($endpoints as [$verb, $path]) {
            $route = Route::getRoutes()->match(Request::create('/api/v1/'.$path, $verb));
            [$controller, $method] = array_pad(explode('@', $route->getActionName()), 2, '__invoke');
            $this->assertTrue(method_exists($controller, $method), $verb.' '.$path.' has no handler');
            if (str_starts_with($path, 'dispatches/')) {
                $this->assertNotSame('show', $method, $verb.' '.$path.' matched the dispatch detail route');
                $this->assertNotSame('update', $method, $verb.' '.$path.' matched the dispatch update route');
            }
            if (str_starts_with($path, 'external/reports/')) {
                $this->assertContains($verb === 'POST' ? \App\Http\Middleware\ValidateExternalApiKey::class : 'signed', $route->gatherMiddleware());
            } elseif (! in_array($path, ['health/live', 'health/ready', 'sms/inbound', 'auth/password-recovery/verify'], true)) {
                $this->assertContains('auth:sanctum', $route->gatherMiddleware());
            }
        }
    }

    public function test_geotag_route_delegates_to_the_existing_workflow(): void
    {
        $this->withoutMiddleware();
        $this->mock(\App\Services\Mobile\HouseholdMobileSetupWorkflow::class, function (\Mockery\MockInterface $mock): void {
            $mock->shouldReceive('updateGeotag')->once()->with(\Mockery::type(Request::class))
                ->andReturn(response()->json(['message' => 'Household geotag saved.']));
        });

        $this->putJson('/api/v1/household/geotag', ['latitude' => 10.3, 'longitude' => 123.9, 'address_label' => 'Test address'])
            ->assertOk()->assertExactJson(['message' => 'Household geotag saved.']);
    }
}
