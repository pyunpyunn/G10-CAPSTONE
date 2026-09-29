<?php

namespace Tests\Feature;

use Tests\TestCase;

class BackendEndpointCharacterizationTest extends TestCase
{
    public function test_high_risk_api_endpoints_keep_their_unauthenticated_json_contract(): void
    {
        $paths = [
            '/api/v1/archive/disaster-events',
            '/api/v1/dashboard',
            '/api/v1/disaster-events',
            '/api/v1/dispatches',
            '/api/v1/households',
            '/api/v1/resource-requests',
            '/api/v1/household/overview',
            '/api/v1/rescuer/overview',
        ];

        foreach ($paths as $path) {
            $this->getJson($path)
                ->assertUnauthorized()
                ->assertExactJson(['message' => 'Unauthenticated.']);
        }
    }
}
