<?php

namespace Tests\Feature;

use App\Http\Resources\FieldCommunicationWorkspaceResource;
use App\Models\User;
use App\Queries\FieldCommunicationQuery;
use App\Queries\RescueDispatchQuery;
use App\Queries\RescuerFieldReportQuery;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ResponseOperationsViewsTest extends TestCase
{
    public function test_field_communications_scope_and_paginate_operational_logs(): void
    {
        Schema::create('responder_communication_logs', function (Blueprint $table) {
            $table->integer('communication_id')->primary(); $table->string('disaster_id')->nullable();
            $table->integer('responder_id'); $table->integer('team_id'); $table->string('team_name');
            $table->text('message'); $table->timestamp('timestamp');
        });
        foreach (range(1, 55) as $id) {
            DB::table('responder_communication_logs')->insert(['communication_id' => $id, 'disaster_id' => 'EV-1',
                'responder_id' => 4, 'team_id' => 2, 'team_name' => 'SAR', 'timestamp' => '2026-10-08 12:00:00',
                'message' => json_encode(['type' => 'ptt_audio', 'channel' => 'command', 'responder_name' => 'Alex', 'audio_path' => 'radio/clip.webm'])]);
        }
        DB::table('responder_communication_logs')->insert(['communication_id' => 56, 'disaster_id' => 'EV-OLD',
            'responder_id' => 4, 'team_id' => 2, 'team_name' => 'SAR', 'timestamp' => '2026-10-08 12:00:00',
            'message' => json_encode(['channel' => 'command'])]);
        $request = Request::create('/', 'GET', ['channel' => 'command', 'team_id' => 2, 'page' => 3, 'per_page' => 20]);
        $logs = app(FieldCommunicationQuery::class)->paginate($request, 'EV-1');
        $this->assertSame(55, $logs->total());
        $this->assertCount(15, $logs->items());
        $response = (new FieldCommunicationWorkspaceResource(['logs' => $logs, 'teams' => [], 'active_event' => null]))->response($request)->getData(true);
        $this->assertSame(3, $response['data']['meta']['current_page']);
        $this->assertSame('Alex', $response['data']['logs'][0]['responder_name']);
        $this->assertStringContainsString('radio/clip.webm', $response['data']['logs'][0]['audio_url']);
        $this->assertSame(0, app(FieldCommunicationQuery::class)->paginate(Request::create('/', 'GET', ['channel' => 'team']), 'EV-1')->total());
        $this->assertSame(0, app(FieldCommunicationQuery::class)->paginate(Request::create('/', 'GET', ['search' => 'missing']), 'EV-1')->total());
    }

    public function test_recordings_filter_runs_before_pagination_and_keeps_event_and_team_scope(): void
    {
        Schema::create('responder_communication_logs', function (Blueprint $table) {
            $table->integer('communication_id')->primary(); $table->string('disaster_id')->nullable();
            $table->integer('responder_id'); $table->integer('team_id'); $table->string('team_name');
            $table->text('message'); $table->timestamp('timestamp');
        });
        $rows = [
            ['EV-1', 2, ['audio_path' => 'radio/older.webm']],
            ['EV-1', 2, ['type' => 'quick_signal']],
            ['EV-1', 2, ['audio_path' => '']],
            ['EV-1', 2, ['audio_path' => null]],
            ['EV-1', 3, ['audio_path' => 'radio/other-team.webm']],
            ['EV-OLD', 2, ['audio_path' => 'radio/old-event.webm']],
            ['EV-1', 2, ['audio_path' => 'radio/latest.webm']],
        ];
        foreach ($rows as $index => [$event, $team, $message]) {
            DB::table('responder_communication_logs')->insert([
                'communication_id' => $index + 1, 'disaster_id' => $event, 'responder_id' => 4,
                'team_id' => $team, 'team_name' => 'Team '.$team, 'message' => json_encode($message),
                'timestamp' => '2026-10-08 12:00:00',
            ]);
        }
        $query = app(FieldCommunicationQuery::class);
        $request = Request::create('/', 'GET', ['recordings_only' => 1, 'team_id' => 2, 'per_page' => 1, 'page' => 2]);
        $logs = $query->paginate($request, 'EV-1');
        $this->assertSame(2, $logs->total());
        $this->assertCount(1, $logs->items());
        $this->assertSame(1, $logs->items()[0]->communication_id);
        $this->assertSame(6, $query->paginate(Request::create('/'), 'EV-1')->total());
    }

    public function test_field_reports_search_before_pagination_and_preserve_mobile_limit(): void
    {
        Schema::create('household_status_logs', function (Blueprint $table) {
            $table->integer('status_log_id')->primary(); $table->string('household_id'); $table->string('disaster_id');
            $table->integer('status_id'); $table->string('source'); $table->text('notes'); $table->timestamp('submitted_at');
        });
        Schema::create('household_statuses', function (Blueprint $table) {
            $table->integer('status_id'); $table->string('status_key'); $table->string('status_label');
        });
        DB::table('household_statuses')->insert(['status_id' => 1, 'status_key' => 'unsafe', 'status_label' => 'Unsafe']);
        foreach (range(1, 60) as $id) {
            DB::table('household_status_logs')->insert(['status_log_id' => $id, 'household_id' => 'HH-'.$id, 'disaster_id' => 'EV-1',
                'status_id' => 1, 'source' => 'responder_field_report', 'notes' => $id === 1 ? 'Older report with target notes' : 'Field update',
                'submitted_at' => '2026-10-08 12:00:00']);
        }
        DB::table('household_status_logs')->insert(['status_log_id' => 61, 'household_id' => 'HH-OTHER', 'disaster_id' => 'EV-OLD',
            'status_id' => 1, 'source' => 'responder_field_report', 'notes' => 'target', 'submitted_at' => '2026-10-08 12:00:00']);
        $query = app(RescuerFieldReportQuery::class);
        $reports = $query->paginateAdmin(['event_id' => 'EV-1', 'status' => 'unsafe', 'search' => 'target'], 20);
        $this->assertSame(1, $reports->total());
        $this->assertSame('HH-1', $reports->items()[0]->household_id);
        $page = $query->paginateAdmin(['event_id' => 'EV-1'], 20);
        $this->assertSame(60, $page->total());
        $this->assertSame('HH-60', $page->items()[0]->household_id);
        $this->assertCount(50, $query->fieldReports(null, null, ['event_id' => 'EV-1']));
    }

    public function test_dispatch_progress_and_coverage_include_completed_work_without_overcounting_teams(): void
    {
        $this->dispatchTables();
        DB::table('rescue_teams')->insert(['team_id' => 1, 'team_code' => 'SAR', 'team_name' => 'Rescue team', 'team_type' => 'Rescue']);
        foreach (['dispatched', 'on_scene', 'returning', 'completed', 'cancelled'] as $index => $status) {
            DB::table('responder_assignments')->insert(['assignment_id' => $index + 1, 'team_id' => 1, 'disaster_id' => 'EV-1',
                'status' => $status, 'household_id' => 'HH-'.$index, 'assigned_at' => '2026-10-08 12:00:00',
                'route_notes' => json_encode(['households_to_cover' => 2]),
                'outcome_notes' => json_encode(['safe_count' => $status === 'completed' ? 50 : 1])]);
        }
        $query = app(RescueDispatchQuery::class);
        $coverage = $query->teamCoverage('EV-1')[0];
        $this->assertSame(8, $coverage['assigned_households']);
        $this->assertSame(5, $coverage['reported_households']);
        $this->assertSame(63, $coverage['coverage_percent']);
        $summary = $query->summary('EV-1', [['team_id' => 1, 'available_responder_count' => 1], ['team_id' => null, 'available_responder_count' => 3]]);
        $this->assertSame(1, $summary['total_teams']);
        $this->assertSame(100, $summary['response_rate']);
        $this->assertSame(5, $summary['dispatch_progress']['total']);
        $this->assertSame(1, $summary['dispatch_progress']['returning']);
        $active = $query->dispatches(Request::create('/', 'GET', ['status' => 'active', 'per_page' => 1]), 'EV-1');
        $this->assertSame(3, $active->total());
        $this->assertCount(1, $active->items());
    }

    public function test_response_read_endpoints_reject_resident_and_rescuer_roles(): void
    {
        Schema::create('users', function (Blueprint $table) { $table->string('user_id')->primary(); $table->string('role'); $table->timestamps(); });
        foreach (['household_resident', 'rescuer'] as $role) {
            Sanctum::actingAs(User::query()->create(['user_id' => $role, 'role' => $role]));
            $this->getJson('/api/v1/dispatches/communications')->assertForbidden();
            $this->getJson('/api/v1/field-reports')->assertForbidden();
        }
    }

    public function test_admin_field_reports_endpoint_exposes_real_pagination_metadata(): void
    {
        Schema::create('users', function (Blueprint $table) { $table->string('user_id')->primary(); $table->string('role'); $table->timestamps(); });
        Schema::create('disaster_events', function (Blueprint $table) {
            $table->string('event_id')->primary(); $table->string('name'); $table->timestamp('started_at'); $table->timestamp('ended_at')->nullable();
        });
        Schema::create('household_status_logs', function (Blueprint $table) {
            $table->integer('status_log_id')->primary(); $table->string('household_id'); $table->string('disaster_id');
            $table->string('source'); $table->text('notes'); $table->timestamp('submitted_at');
        });
        DB::table('disaster_events')->insert(['event_id' => 'EV-1', 'name' => 'Flood response', 'started_at' => now()]);
        foreach (range(1, 25) as $id) DB::table('household_status_logs')->insert(['status_log_id' => $id,
            'household_id' => 'HH-'.$id, 'disaster_id' => 'EV-1', 'source' => 'responder_field_report', 'notes' => 'Field update', 'submitted_at' => now()]);
        Sanctum::actingAs(User::query()->create(['user_id' => 'ADMIN', 'role' => 'admin']));
        $this->getJson('/api/v1/field-reports?page=2&per_page=20')->assertOk()
            ->assertJsonPath('data.active_event.event_id', 'EV-1')
            ->assertJsonPath('data.reports_meta.total', 25)->assertJsonPath('data.reports_meta.current_page', 2)
            ->assertJsonCount(5, 'data.reports');
        $this->getJson('/api/v1/field-reports?per_page=101')->assertUnprocessable();
    }

    private function dispatchTables(): void
    {
        Schema::create('rescue_teams', function (Blueprint $table) {
            $table->integer('team_id')->primary(); $table->string('team_code'); $table->string('team_name'); $table->string('team_type');
        });
        Schema::create('responders', function (Blueprint $table) {
            $table->integer('responder_id')->primary(); $table->string('full_name'); $table->string('contact_number')->nullable();
        });
        Schema::create('responder_assignments', function (Blueprint $table) {
            $table->integer('assignment_id')->primary(); $table->integer('team_id')->nullable(); $table->integer('responder_id')->nullable();
            foreach (['assignment_code', 'dispatch_type', 'disaster_id', 'household_id', 'assigned_area', 'priority_level', 'status'] as $column) $table->string($column)->nullable();
            $table->integer('responder_count')->nullable();
            foreach (['route_notes', 'dispatch_notes', 'outcome_notes'] as $column) $table->text($column)->nullable();
            foreach (['assigned_at', 'accepted_at', 'en_route_at', 'arrived_at', 'completed_at', 'updated_at'] as $column) $table->timestamp($column)->nullable();
        });
    }
}
