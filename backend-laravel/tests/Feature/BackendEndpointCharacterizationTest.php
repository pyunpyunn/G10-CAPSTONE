<?php

namespace Tests\Feature;

use App\Http\Requests\ListRequest;
use Illuminate\Support\Facades\Validator;
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

    public function test_shared_list_pagination_rules_and_internal_clamps_are_bounded(): void
    {
        $this->assertTrue(Validator::make([
            'page' => 1,
            'per_page' => 100,
        ], ListRequest::paginationRules())->passes());

        $this->assertFalse(Validator::make([
            'page' => 0,
            'per_page' => 101,
        ], ListRequest::paginationRules())->passes());

        $this->assertSame(1, ListRequest::clampPage(-10));
        $this->assertSame(1, ListRequest::clampPerPage(0));
        $this->assertSame(100, ListRequest::clampPerPage(500));
        $this->assertSame(15, ListRequest::clampPerPage(null));
    }
}
