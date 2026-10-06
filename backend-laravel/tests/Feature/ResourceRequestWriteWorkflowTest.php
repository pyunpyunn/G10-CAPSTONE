<?php

namespace Tests\Feature;

use App\Models\ResourceRequest;
use App\Services\Web\ResourceRequestWriteWorkflow;
use App\Services\Shared\TrackingAidForwardingService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class ResourceRequestWriteWorkflowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('resource_request_status', function (Blueprint $table): void { $table->increments('status_id'); $table->string('status_key'); $table->string('status_label'); });
        Schema::create('urgency_levels', function (Blueprint $table): void { $table->increments('urgency_id'); $table->string('urgency_key'); $table->string('urgency_label'); });
        Schema::create('resource_requests', function (Blueprint $table): void { $table->string('request_id')->primary(); $table->string('request_source')->nullable(); $table->string('source_reference')->nullable(); $table->string('request_category')->nullable(); $table->string('evacuation_center_id')->nullable(); $table->string('requested_by')->nullable(); $table->string('handled_by')->nullable(); $table->string('resource_type')->nullable(); $table->string('item_name')->nullable(); $table->integer('quantity')->nullable(); $table->string('unit')->nullable(); $table->text('description')->nullable(); $table->integer('urgency_id')->nullable(); $table->integer('status_id')->nullable(); $table->string('validation_status')->nullable(); $table->text('validation_notes')->nullable(); $table->string('validated_by_user_id')->nullable(); $table->timestamp('validated_at')->nullable(); $table->timestamp('released_for_tracking_at')->nullable(); $table->string('tracking_reference')->nullable(); $table->timestamps(); });
        Schema::create('evacuation_centers', function (Blueprint $table): void { $table->string('evacuation_center_id')->primary(); $table->string('name')->nullable(); $table->string('osm_address')->nullable(); $table->string('current_event_id')->nullable(); $table->softDeletes(); });
        Schema::create('disaster_events', function (Blueprint $table): void { $table->string('event_id')->primary(); $table->string('name')->nullable(); $table->timestamp('ended_at')->nullable(); $table->timestamp('deleted_at')->nullable(); });        Schema::create('request_validations', function (Blueprint $table): void { $table->increments('validation_id'); $table->string('request_id'); $table->string('validation_status'); $table->string('validator_user_id')->nullable(); $table->text('validation_notes')->nullable(); $table->text('missing_information')->nullable(); $table->string('duplicate_request_id')->nullable(); $table->timestamp('validated_at')->nullable(); $table->timestamps(); });
        Schema::create('audit_logs', function (Blueprint $table): void { $table->increments('audit_id'); $table->string('user_id')->nullable(); $table->string('role_key')->nullable(); $table->string('module'); $table->string('action'); $table->string('reference_table'); $table->string('reference_id'); $table->text('old_values')->nullable(); $table->text('new_values')->nullable(); $table->string('ip_address')->nullable(); $table->string('user_agent')->nullable(); $table->timestamp('created_at')->nullable(); });
        Schema::create('jobs', function (Blueprint $table): void { $table->bigIncrements('id'); $table->string('queue')->index(); $table->longText('payload'); $table->unsignedTinyInteger('attempts'); $table->unsignedInteger('reserved_at')->nullable(); $table->unsignedInteger('available_at'); $table->unsignedInteger('created_at'); });
        DB::table('resource_request_status')->insert([['status_id'=>1,'status_key'=>'pending','status_label'=>'Pending'],['status_id'=>2,'status_key'=>'approved','status_label'=>'Approved']]);
        DB::table('urgency_levels')->insert(['urgency_id'=>1,'urgency_key'=>'medium','urgency_label'=>'Medium']);
        ResourceRequest::create(['request_id'=>'RR-1','request_source'=>'field_team','request_category'=>'resource','requested_by'=>'Requester','resource_type'=>'Water','quantity'=>2,'unit'=>'boxes','urgency_id'=>1,'status_id'=>1,'validation_status'=>'needs_validation']);
    }

    public function test_validation_workflow_updates_status_history_and_syncs_tracking_mirror(): void
    {
        $tracking = $this->createMock(TrackingAidForwardingService::class);
        $tracking->expects($this->once())->method('syncRequestStatus')->with('RR-1', 'verified', $this->isInstanceOf(\Illuminate\Support\Carbon::class));
        $workflow = new ResourceRequestWriteWorkflow($tracking, app(\App\Queries\ResourceRequestQuery::class), app(\App\Presenters\ResourceRequestPresenter::class));
        $request = Request::create('/api/v1/resource-requests/RR-1/validate', 'POST', ['validation_status'=>'verified','validation_notes'=>'Confirmed']);

        $result = $workflow->validate($request, 'RR-1', ResourceRequest::query()->where('request_id','RR-1')->first(), ['validation_status'=>'verified','validation_notes'=>'Confirmed']);

        $this->assertSame('RR-1', $result['request_id']);
        $this->assertSame('verified', DB::table('resource_requests')->where('request_id','RR-1')->value('validation_status'));
        $this->assertSame('Confirmed', DB::table('request_validations')->where('request_id','RR-1')->value('validation_notes'));
        $this->assertSame('validate', DB::table('audit_logs')->value('action'));
    }

    public function test_tracking_sync_is_queued_in_the_local_transaction_and_rolls_back_with_it(): void
    {
        $tracking = app(TrackingAidForwardingService::class);

        try {
            DB::transaction(function () use ($tracking): void {
                $tracking->syncRequestStatus('RR-1', 'verified');
                $this->assertSame(1, DB::table('jobs')->where('queue', 'trackingaid')->count());
                throw new RuntimeException('Simulated local write failure');
            });
            $this->fail('The transaction should fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated local write failure', $exception->getMessage());
        }

        $this->assertSame(0, DB::table('jobs')->count());

        DB::transaction(fn () => $tracking->syncRequestStatus('RR-1', 'verified'));
        $this->assertSame(1, DB::table('jobs')->where('queue', 'trackingaid')->count());
    }

    public function test_tracking_mirror_reads_requests_in_bounded_pages(): void
    {
        for ($page = 0; $page < 3; $page++) {
            $rows = [];
            for ($offset = 0; $offset < 400; $offset++) {
                $number = $page * 400 + $offset;
                $rows[] = [
                    'request_id' => sprintf('RR-%05d', $number),
                    'validation_status' => 'needs_validation',
                    'resource_type' => 'Water',
                    'item_name' => 'Water container',
                    'quantity' => 2,
                    'unit' => 'boxes',
                ];
            }
            DB::table('resource_requests')->insert($rows);
        }

        $pageSizes = [];
        DB::listen(function ($query) use (&$pageSizes): void {
            if (str_contains($query->sql, 'from "resource_requests"') && str_contains($query->sql, 'limit')) {
                $pageSizes[] = $query->sql;
            }
        });

        $items = app(\App\Queries\ResourceRequestMirror::class)->summarize();
        $water = collect($items)->firstWhere('label', 'Water containers');

        $this->assertSame('1201 open request(s)', $water['status']);
        $this->assertCount(3, $pageSizes);
        foreach ($pageSizes as $sql) {
            $this->assertStringContainsString('limit 500', $sql);
        }
    }
}



