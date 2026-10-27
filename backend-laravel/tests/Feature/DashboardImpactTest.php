<?php

namespace Tests\Feature;

use App\Queries\DashboardImpactQuery;
use App\Queries\DashboardQuery;
use App\Services\Shared\BarangayProfileService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DashboardImpactTest extends TestCase
{
    public function test_history_counts_latest_household_assessments_all_damage_and_completed_events_only(): void
    {
        $this->mock(BarangayProfileService::class, fn ($mock) => $mock->shouldReceive('current')->andReturn(['barangay_id' => 1]));
        Schema::create('sitios', function ($t) { $t->integer('sitio_id'); $t->integer('barangay_id'); });
        Schema::create('puroks', function ($t) { $t->integer('purok_id'); $t->integer('sitio_id'); });
        Schema::create('addresses', function ($t) { $t->integer('address_id'); $t->integer('purok_id'); });
        Schema::create('households', function ($t) { $t->string('household_id'); $t->integer('address_id'); });
        Schema::create('disaster_events', function ($t) { $t->string('event_id'); $t->timestamp('ended_at')->nullable(); $t->timestamp('deleted_at')->nullable(); });
        Schema::create('responder_field_reports', function ($t) {
            $t->integer('report_id'); $t->string('disaster_id'); $t->string('household_id');
            $t->integer('death_count'); $t->integer('injured_count'); $t->string('property_damage');
        });
        Schema::create('responder_field_report_members', function ($t) { $t->integer('report_id'); $t->string('status_key'); });
        DB::table('sitios')->insert([['sitio_id' => 1, 'barangay_id' => 1], ['sitio_id' => 2, 'barangay_id' => 2]]);
        DB::table('puroks')->insert([['purok_id' => 1, 'sitio_id' => 1], ['purok_id' => 2, 'sitio_id' => 2]]);
        DB::table('addresses')->insert([['address_id' => 1, 'purok_id' => 1], ['address_id' => 2, 'purok_id' => 2]]);
        foreach (['H1', 'H2', 'H3', 'H4'] as $id) DB::table('households')->insert(['household_id' => $id, 'address_id' => 1]);
        DB::table('households')->insert(['household_id' => 'OUTSIDE', 'address_id' => 2]);
        DB::table('disaster_events')->insert([
            ['event_id' => 'E1', 'ended_at' => '2026-10-04 10:00:00', 'deleted_at' => null],
            ['event_id' => 'E2', 'ended_at' => '2026-11-04 10:00:00', 'deleted_at' => null],
            ['event_id' => 'ACTIVE', 'ended_at' => null, 'deleted_at' => null],
            ['event_id' => 'DELETED', 'ended_at' => '2026-10-04 10:00:00', 'deleted_at' => now()],
        ]);
        $report = fn ($id, $event, $household, $deaths, $injured, $damage) => DB::table('responder_field_reports')->insert([
            'report_id' => $id, 'disaster_id' => $event, 'household_id' => $household,
            'death_count' => $deaths, 'injured_count' => $injured, 'property_damage' => $damage,
        ]);
        $report(1, 'E1', 'H1', 99, 99, 'major_damage');
        $report(2, 'E1', 'H1', 1, 2, 'minor_damage');
        $report(3, 'E1', 'H2', 0, 0, 'major_damage');
        $report(4, 'E1', 'H3', 0, 0, 'completely_destroyed');
        $report(5, 'E1', 'H4', 0, 0, 'not_yet_assessed');
        $report(6, 'E2', 'H1', 0, 1, 'no_damage');
        $report(7, 'ACTIVE', 'H1', 50, 50, 'minor_damage');
        $report(8, 'E1', 'OUTSIDE', 50, 50, 'minor_damage');
        $report(9, 'DELETED', 'H1', 50, 50, 'minor_damage');
        DB::table('responder_field_report_members')->insert([['report_id' => 1, 'status_key' => 'missing'], ['report_id' => 2, 'status_key' => 'missing']]);
        $query = app(DashboardImpactQuery::class);
        $monthly = $query->history('monthly');
        $this->assertSame(['deaths' => 1, 'injured' => 3, 'missing' => 1, 'damaged_houses' => 3], $monthly['totals']);
        $this->assertSame(2, $monthly['event_count']);
        $this->assertSame(5, $monthly['assessed_households']);
        $this->assertCount(2, $monthly['periods']);
        $this->assertSame('Oct 2026', $monthly['periods'][0]['label']);
        $this->assertSame('2026-09-28', $query->history('weekly')['periods'][0]['key']);
        $yearly = $query->history('yearly');
        $this->assertCount(1, $yearly['periods']);
        $this->assertSame(2, $yearly['periods'][0]['event_count']);
        $this->assertSame($monthly['totals'], $yearly['totals']);
    }

    public function test_team_totals_count_each_team_once_despite_multiple_assignments(): void
    {
        Schema::create('rescue_teams', function ($t) { $t->string('team_id'); $t->string('duty_status'); });
        Schema::create('responder_assignments', function ($t) { $t->string('team_id'); $t->string('disaster_id'); $t->string('status'); $t->integer('assignment_id')->nullable(); $t->timestamp('assigned_at')->nullable(); });
        DB::table('rescue_teams')->insert([
            ['team_id' => 'T1', 'duty_status' => 'available'], ['team_id' => 'T2', 'duty_status' => 'available'],
            ['team_id' => 'T3', 'duty_status' => 'unavailable'], ['team_id' => 'T4', 'duty_status' => 'available'],
        ]);
        DB::table('responder_assignments')->insert([
            ['team_id' => 'T1', 'disaster_id' => 'E1', 'status' => 'assigned'],
            ['team_id' => 'T1', 'disaster_id' => 'E1', 'status' => 'on_scene'],
            ['team_id' => 'T1', 'disaster_id' => 'E1', 'status' => 'on_scene'],
            ['team_id' => 'T2', 'disaster_id' => 'E1', 'status' => 'en_route'],
            ['team_id' => 'T4', 'disaster_id' => 'OTHER', 'status' => 'on_scene'],
        ]);
        $summary = app(DashboardQuery::class)->getTeamCounts('E1');
        $this->assertSame(4, $summary['total_teams']);
        $this->assertSame([1, 1, 0, 1, 1], array_column($summary['team_counts'], 'value'));
        $this->assertSame([0, 0, 0, 3, 1], array_column(app(DashboardQuery::class)->getTeamCounts(null)['team_counts'], 'value'));
        DB::table('responder_assignments')->insert([
            ['team_id' => 'T4', 'disaster_id' => 'E1', 'status' => 'completed', 'assigned_at' => '2026-10-26 10:00:00', 'assignment_id' => 10],
            ['team_id' => 'T4', 'disaster_id' => 'E1', 'status' => 'completed', 'assigned_at' => '2026-10-27 10:00:00', 'assignment_id' => 11],
            ['team_id' => 'T1', 'disaster_id' => 'E1', 'status' => 'completed', 'assigned_at' => '2026-10-27 10:00:00', 'assignment_id' => 12],
        ]);
        $summary = app(DashboardQuery::class)->getTeamCounts('E1');
        $this->assertSame([1, 1, 1, 0, 1], array_column($summary['team_counts'], 'value'));
        $this->assertSame($summary['total_teams'], array_sum(array_column($summary['team_counts'], 'value')));

    }
}
