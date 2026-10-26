<?php

namespace Tests\Feature;

use App\Models\User;
use App\Presenters\HouseholdMobilePresenter;
use App\Queries\HouseholdMobileReadQuery;
use App\Services\Mobile\EvacuationCheckInService;
use App\Services\Mobile\HouseholdMobileReadWorkflow;
use App\Services\Mobile\HouseholdMobileSupport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Tests\TestCase;

class HouseholdQrIdentityTest extends TestCase
{
    public function test_qr_is_unique_per_household_and_stable_across_events(): void
    {
        $presenter = new HouseholdMobilePresenter();
        $first = (object) ['household_id' => 'HH-001', 'household_name' => 'First family'];
        $second = (object) ['household_id' => 'HH-002', 'household_name' => 'Second family'];
        $qr = $presenter->qrPayload($first, null);
        $this->assertSame('HH-001', $qr['value']);
        $this->assertSame($qr['household_id'], $qr['value']);
        $this->assertNotSame($qr['value'], $presenter->qrPayload($second, null)['value']);
        $this->assertSame($qr['value'], $presenter->qrPayload($first, ['event_id' => 'EVENT-NEW'])['value']);
    }

    public function test_qr_endpoint_uses_authenticated_household_even_when_another_id_is_requested(): void
    {
        $query = $this->mock(HouseholdMobileReadQuery::class);
        $query->shouldReceive('householdRecord')->once()->with('HH-OWN')->andReturn((object) ['household_id' => 'HH-OWN', 'household_name' => 'Own family']);
        $query->shouldReceive('activeEvent')->once()->andReturn(null);
        $workflow = new HouseholdMobileReadWorkflow(new HouseholdMobileSupport(), $query, new HouseholdMobilePresenter());
        $request = Request::create('/household/qr', 'GET', ['household_id' => 'HH-OTHER']);
        $user = new User();
        $user->setAttribute('household_id', 'HH-OWN');
        $request->setUserResolver(fn () => $user);
        $qr = $workflow->qr($request)->getData(true)['data']['qr'];
        $this->assertSame('HH-OWN', $qr['value']);
        $this->assertSame('HH-OWN', $qr['household_id']);
    }

    public function test_scanning_generated_qr_verifies_its_exact_household_id(): void
    {
        $service = new class extends EvacuationCheckInService {
            public function store(Request $request): JsonResponse
            {
                return response()->json(['household_id' => $request->input('household_id'), 'method' => $request->input('method')]);
            }
        };
        foreach (['HH-001', 'HH-002', '20240001'] as $id) {
            $qr = (new HouseholdMobilePresenter())->qrPayload((object) ['household_id' => $id, 'household_name' => 'Family'], null);
            $request = Request::create('/verify-qr', 'POST', ['qr_payload' => $qr['value']]);
            $result = $service->verifyQr($request)->getData(true);
            $this->assertSame($id, $result['household_id']);
            $this->assertSame('qr_scan', $result['method']);
        }
    }
}
