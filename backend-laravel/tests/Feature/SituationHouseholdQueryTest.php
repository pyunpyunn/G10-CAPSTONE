<?php

namespace Tests\Feature;

use App\Queries\SituationHouseholdQuery;
use App\Queries\HouseholdEventStatusQuery;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SituationHouseholdQueryTest extends TestCase
{
    public function test_household_details_use_event_assessments_latest_log_and_registered_members(): void
    {
        Schema::create('households', function ($t) { $t->string('household_id'); $t->string('household_code'); });
        Schema::create('household_status_logs', function ($t) { $t->integer('status_log_id'); $t->text('notes')->nullable(); $t->timestamp('submitted_at')->nullable(); });
        Schema::create('household_members', function ($t) {
            $t->integer('member_id'); $t->string('household_id'); $t->string('first_name'); $t->string('last_name'); $t->date('birth_date')->nullable(); $t->timestamp('deleted_at')->nullable();
        });
        Schema::create('member_statuses', function ($t) { $t->integer('status_id'); $t->string('status_key'); });
        Schema::create('member_disaster_statuses', function ($t) { $t->integer('member_status_id'); $t->string('household_id'); $t->integer('member_id'); $t->string('disaster_id'); $t->integer('status_id'); });
        Schema::create('responder_field_reports', function ($t) { $t->integer('report_id'); $t->integer('status_log_id'); $t->string('property_damage'); $t->integer('injured_count'); });
        DB::table('household_status_logs')->insert(['status_log_id' => 10, 'notes' => json_encode(['members' => [['member_id' => 1, 'status_key' => 'injured']], 'user_notes' => 'Medical help']), 'submitted_at' => '2026-10-18 10:00:00']);
        DB::table('responder_field_reports')->insert(['report_id' => 1, 'status_log_id' => 10, 'property_damage' => 'major_damage', 'injured_count' => 1]);
        DB::table('household_members')->insert([
            ['member_id' => 1, 'household_id' => 'HH1', 'first_name' => 'Ana', 'last_name' => 'Cruz', 'birth_date' => '2000-01-01', 'deleted_at' => null],
            ['member_id' => 2, 'household_id' => 'HH1', 'first_name' => 'Ben', 'last_name' => 'Cruz', 'birth_date' => null, 'deleted_at' => null],
            ['member_id' => 3, 'household_id' => 'HH1', 'first_name' => 'Deleted', 'last_name' => 'Cruz', 'birth_date' => null, 'deleted_at' => now()],
        ]);
        DB::table('member_statuses')->insert([['status_id' => 1, 'status_key' => 'safe'], ['status_id' => 2, 'status_key' => 'missing']]);
        DB::table('member_disaster_statuses')->insert([
            ['member_status_id' => 1, 'household_id' => 'HH1', 'member_id' => 1, 'disaster_id' => 'EV1', 'status_id' => 1],
            ['member_status_id' => 2, 'household_id' => 'HH1', 'member_id' => 2, 'disaster_id' => 'EV1', 'status_id' => 2],
            ['member_status_id' => 3, 'household_id' => 'HH1', 'member_id' => 2, 'disaster_id' => 'OTHER-EVENT', 'status_id' => 1],
        ]);
        $base = \Mockery::mock();
        $base->shouldReceive('whereIn')->with('hs.status_key', HouseholdEventStatusQuery::UNSAFE_KEYS)->andReturnSelf();
        $base->shouldReceive('orderBy')->andReturnSelf();
        $base->shouldReceive('get')->andReturn(collect([(object) ['household_id' => 'HH1', 'household_code' => 'HH-ONE', 'sitio_id' => 'S1', 'sitio_name' => 'Upper', 'purok_id' => 'P1', 'purok_name' => 'One', 'status_key' => 'unsafe', 'status_log_id' => 10]]));
        $row = app(SituationHouseholdQuery::class)->rows($base, 'EV1')[0];
        $this->assertSame(2, $row['registered_members']);
        $this->assertSame(2, $row['affected_members']);
        $this->assertSame('injured', $row['members'][0]['status']);
        $this->assertSame(1, $row['missing']);
        $this->assertSame(1, $row['injured']);
        $this->assertSame('major_damage', $row['damage']);
        $this->assertSame('Medical help', $row['immediate_needs']);
        DB::table('member_disaster_statuses')->where('disaster_id', 'EV1')->where('member_id', 2)->delete();
        $row = app(SituationHouseholdQuery::class)->rows($base, 'EV1')[0];
        $this->assertNull($row['affected_members']);
        $this->assertNull($row['missing']);
    }
}
