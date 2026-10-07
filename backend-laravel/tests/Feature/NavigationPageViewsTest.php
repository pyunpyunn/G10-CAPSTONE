<?php

namespace Tests\Feature;

use App\Data\MappingSnapshot;
use App\Http\Resources\MappingSnapshotResource;
use App\Presenters\RescuerAccountPresenter;
use App\Queries\RescuerAccountQuery;
use App\Services\Mobile\RescuerAccountAuditLogger;
use App\Services\Mobile\RescuerAccountSupport;
use App\Services\Mobile\RescuerAccountWriteWorkflow;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NavigationPageViewsTest extends TestCase
{
    public function test_rescue_office_layer_uses_only_valid_configured_coordinates(): void
    {
        $snapshot = new MappingSnapshot(null, [], [], [], true);
        config(['rescue_office' => ['name' => 'HQ', 'address' => 'Office address', 'latitude' => '10.1', 'longitude' => '123.2']]);
        $data = (new MappingSnapshotResource($snapshot))->resolve(Request::create('/'));
        $this->assertSame(10.1, $data['rescue_offices'][0]['latitude']);
        $this->assertSame('HQ', $data['rescue_offices'][0]['name']);
        foreach ([null, '', 'invalid', 91] as $latitude) {
            config(['rescue_office.latitude' => $latitude]);
            $this->assertSame([], (new MappingSnapshotResource($snapshot))->resolve(Request::create('/'))['rescue_offices']);
        }
    }

    public function test_delete_removes_user_from_roster_and_preserves_history(): void
    {
        $this->accountTables();
        $audit = $this->createMock(RescuerAccountAuditLogger::class);
        $audit->expects($this->once())->method('writeAuditLog');
        $workflow = $this->workflow($audit);
        $response = $workflow->delete(Request::create('/'), 1);
        $this->assertSame(200, $response->status());
        $this->assertNotNull(DB::table('responders')->value('deleted_at'));
        $this->assertSame(0, DB::table('users')->value('is_active'));
        $this->assertNull(DB::table('rescue_teams')->value('leader_responder_id'));
        $this->assertSame(1, DB::table('responder_assignments')->count());
        $this->assertSame(404, $workflow->delete(Request::create('/'), 1)->status());
    }

    public function test_delete_rejects_active_dispatch_without_changing_account(): void
    {
        $this->accountTables();
        DB::table('responder_assignments')->update(['status' => 'en_route']);
        $audit = $this->createMock(RescuerAccountAuditLogger::class);
        $audit->expects($this->never())->method('writeAuditLog');
        $response = $this->workflow($audit)->delete(Request::create('/'), 1);
        $this->assertSame(422, $response->status());
        $this->assertNull(DB::table('responders')->value('deleted_at'));
        $this->assertSame(1, DB::table('users')->value('is_active'));
    }

    public function test_request_workflows_filter_before_pagination(): void
    {
        Schema::create('resource_requests', function (Blueprint $table) {
            $table->string('request_id'); $table->string('validation_status'); $table->integer('status_id')->nullable();
            $table->integer('urgency_id')->nullable(); $table->string('evacuation_center_id')->nullable();
            $table->timestamp('created_at')->nullable();
        });
        Schema::create('resource_request_status', function (Blueprint $table) {
            $table->integer('status_id'); $table->string('status_key'); $table->string('status_label');
        });
        Schema::create('urgency_levels', function (Blueprint $table) {
            $table->integer('urgency_id'); $table->string('urgency_key'); $table->string('urgency_label');
        });
        Schema::create('evacuation_centers', function (Blueprint $table) {
            $table->string('evacuation_center_id'); $table->string('name'); $table->string('osm_address'); $table->string('current_event_id');
        });
        Schema::create('disaster_events', function (Blueprint $table) {
            $table->string('event_id'); $table->string('name'); $table->timestamp('ended_at')->nullable(); $table->timestamp('deleted_at')->nullable();
        });
        foreach (['needs_validation', 'returned', 'verified', 'forwarded', 'fulfilled', 'cancelled'] as $index => $status) {
            DB::table('resource_requests')->insert(['request_id' => 'RR-'.$index, 'validation_status' => $status, 'created_at' => now()]);
        }
        $presenter = $this->createMock(\App\Presenters\ResourceRequestPresenter::class);
        foreach (['statusKey', 'sourceKey', 'categoryKey'] as $method) $presenter->method($method)->willReturnArgument(0);
        $query = new \App\Queries\ResourceRequestQuery($presenter, $this->createMock(\App\Services\Shared\TrackingAidForwardingService::class));
        [$approval] = $query->list(Request::create('/', 'GET', ['workflow' => 'approval', 'per_page' => 1]));
        $this->assertSame(2, $approval->total());
        $this->assertSame('needs_validation', $approval->items()[0]->validation_status);
        [$monitoring] = $query->list(Request::create('/', 'GET', ['workflow' => 'monitoring', 'per_page' => 1]));
        $this->assertSame(3, $monitoring->total());
        $this->assertSame('verified', $monitoring->items()[0]->validation_status);
        [$completed] = $query->list(Request::create('/', 'GET', ['workflow' => 'monitoring', 'status' => 'fulfilled']));
        $this->assertSame(1, $completed->total());
        $this->assertSame('fulfilled', $completed->items()[0]->validation_status);
    }

    private function workflow(RescuerAccountAuditLogger $audit): RescuerAccountWriteWorkflow
    {
        return new RescuerAccountWriteWorkflow($this->createMock(RescuerAccountSupport::class),
            $this->createMock(RescuerAccountQuery::class), $this->createMock(RescuerAccountPresenter::class), $audit);
    }

    private function accountTables(): void
    {
        Schema::create('responders', function (Blueprint $table) {
            $table->integer('responder_id'); $table->string('user_id'); $table->boolean('is_deployed');
            $table->boolean('is_validated'); $table->string('duty_status'); $table->integer('team_id')->nullable();
            $table->timestamp('deleted_at')->nullable(); $table->timestamp('updated_at')->nullable();
        });
        Schema::create('users', function (Blueprint $table) {
            $table->string('user_id'); $table->boolean('is_active'); $table->timestamp('updated_at')->nullable();
        });
        Schema::create('rescue_teams', fn (Blueprint $table) => $table->integer('leader_responder_id')->nullable());
        Schema::create('responder_assignments', function (Blueprint $table) { $table->integer('responder_id'); $table->string('status'); });
        DB::table('responders')->insert(['responder_id' => 1, 'user_id' => 'U1', 'is_deployed' => false,
            'is_validated' => true, 'duty_status' => 'available', 'team_id' => 1]);
        DB::table('users')->insert(['user_id' => 'U1', 'is_active' => true]);
        DB::table('rescue_teams')->insert(['leader_responder_id' => 1]);
        DB::table('responder_assignments')->insert(['responder_id' => 1, 'status' => 'completed']);
    }
}
