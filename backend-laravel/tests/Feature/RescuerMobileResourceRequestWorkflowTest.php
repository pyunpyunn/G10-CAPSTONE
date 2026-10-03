<?php

namespace Tests\Feature;

use App\Services\Mobile\RescuerMobileResourceRequestWorkflow;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RescuerMobileResourceRequestWorkflowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('resource_requests', function (Blueprint $table): void {
            $table->string('request_id')->primary();
            $table->string('request_source')->nullable();
            $table->string('source_reference')->nullable();
            $table->string('request_category')->nullable();
            $table->string('evacuation_center_id')->nullable();
            $table->string('requested_by')->nullable();
            $table->string('handled_by')->nullable();
            $table->string('resource_type')->nullable();
            $table->string('item_name')->nullable();
            $table->integer('quantity')->nullable();
            $table->string('unit')->nullable();
            $table->text('description')->nullable();
            $table->integer('urgency_id')->nullable();
            $table->integer('status_id')->nullable();
            $table->string('validation_status')->nullable();
            $table->timestamps();
        });
        Schema::create('resource_request_status', function (Blueprint $table): void {
            $table->increments('status_id');
            $table->string('status_key');
        });
        Schema::create('urgency_levels', function (Blueprint $table): void {
            $table->increments('urgency_id');
            $table->string('urgency_key');
        });

        DB::table('resource_request_status')->insert([
            ['status_id' => 1, 'status_key' => 'needs_validation'],
            ['status_id' => 2, 'status_key' => 'cancelled'],
        ]);
        DB::table('urgency_levels')->insert([
            ['urgency_id' => 1, 'urgency_key' => 'low'],
            ['urgency_id' => 2, 'urgency_key' => 'medium'],
            ['urgency_id' => 3, 'urgency_key' => 'high'],
        ]);
    }

    public function test_store_keeps_success_contract_and_persists_the_composed_location(): void
    {
        $request = Request::create('/api/v1/rescuer/resource-requests', 'POST', [
            'location' => 'Zone 2',
            'request_category' => 'resource',
            'resource_type' => 'Water',
            'item_name' => 'Drinking water',
            'quantity' => 5,
            'unit' => 'packs',
            'description' => 'Family supply',
            'urgency_key' => 'high',
        ]);

        $response = app(RescuerMobileResourceRequestWorkflow::class)->store($request);
        $requestId = $response->getData(true)['data']['request_id'];

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame([
            'message' => 'Resource request submitted for HQ validation.',
            'data' => ['request_id' => $requestId],
        ], $response->getData(true));
        $this->assertMatchesRegularExpression('/^RR-\d{4}-[A-Z0-9]{6}$/', $requestId);
        $this->assertDatabaseHas('resource_requests', [
            'request_id' => $requestId,
            'request_source' => 'rescuer_mobile',
            'request_category' => 'resource',
            'resource_type' => 'Water',
            'urgency_id' => 3,
            'status_id' => 1,
            'validation_status' => 'needs_validation',
            'description' => "Location: Zone 2\nFamily supply",
        ]);
    }

    public function test_cancel_keeps_not_found_conflict_and_success_contracts(): void
    {
        $workflow = app(RescuerMobileResourceRequestWorkflow::class);

        $notFound = $workflow->cancel(Request::create('/api/v1/rescuer/resource-requests/MISSING', 'PATCH'), 'MISSING');
        $this->assertSame(404, $notFound->getStatusCode());
        $this->assertSame(['message' => 'Resource request was not found for your account.'], $notFound->getData(true));

        DB::table('resource_requests')->insert([
            'request_id' => 'RR-CANCEL-1',
            'handled_by' => null,
            'validation_status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $conflict = $workflow->cancel(Request::create('/api/v1/rescuer/resource-requests/RR-CANCEL-1', 'PATCH'), 'RR-CANCEL-1');
        $this->assertSame(409, $conflict->getStatusCode());
        $this->assertSame(['message' => 'Only requests still waiting for validation can be cancelled from mobile.'], $conflict->getData(true));

        DB::table('resource_requests')->insert([
            'request_id' => 'RR-CANCEL-2',
            'handled_by' => null,
            'validation_status' => 'needs_validation',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $cancelled = $workflow->cancel(Request::create('/api/v1/rescuer/resource-requests/RR-CANCEL-2', 'PATCH'), 'RR-CANCEL-2');

        $this->assertSame(200, $cancelled->getStatusCode());
        $this->assertSame(['message' => 'Resource request cancelled.'], $cancelled->getData(true));
        $this->assertDatabaseHas('resource_requests', [
            'request_id' => 'RR-CANCEL-2',
            'validation_status' => 'cancelled',
            'status_id' => 2,
        ]);
    }
}



