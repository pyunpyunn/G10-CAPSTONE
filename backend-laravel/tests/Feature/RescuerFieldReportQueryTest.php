<?php

namespace Tests\Feature;

use App\Http\Resources\RescuerMobileFieldReportResource;
use App\Queries\RescuerFieldReportQuery;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RescuerFieldReportQueryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('household_status_logs', function (Blueprint $table): void {
            $table->increments('status_log_id');
            $table->string('household_id');
            $table->string('disaster_id');
            $table->unsignedInteger('status_id');
            $table->string('source');
            $table->text('notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
        });
        Schema::create('household_statuses', function (Blueprint $table): void {
            $table->increments('status_id');
            $table->string('status_key');
            $table->string('status_label')->nullable();
        });
        Schema::create('households', function (Blueprint $table): void {
            $table->string('household_id')->primary();
            $table->string('household_code')->nullable();
            $table->string('household_name')->nullable();
        });
        Schema::create('responder_field_reports', function (Blueprint $table): void {
            $table->increments('report_id');
            $table->unsignedInteger('status_log_id');
            $table->unsignedInteger('assignment_id')->nullable();
            $table->string('incident_type')->nullable();
            $table->string('incident_type_other')->nullable();
            $table->string('property_damage')->nullable();
            $table->unsignedInteger('death_count')->nullable();
            $table->unsignedInteger('injured_count')->nullable();
            $table->unsignedInteger('missing_count')->nullable();
        });
        Schema::create('responder_field_report_members', function (Blueprint $table): void {
            $table->increments('detail_id');
            $table->unsignedInteger('report_id');
            $table->string('household_id');
            $table->string('member_id');
            $table->unsignedInteger('status_id')->nullable();
            $table->string('status_key');
        });
        Schema::create('household_members', function (Blueprint $table): void {
            $table->string('member_id')->primary();
            $table->string('household_id');
            $table->string('first_name')->nullable();
            $table->string('middle_name')->nullable();
            $table->string('last_name')->nullable();
        });
        Schema::create('member_statuses', function (Blueprint $table): void {
            $table->increments('status_id');
            $table->string('status_key');
            $table->string('status_label')->nullable();
        });

        $notes = json_encode([
            'report_type' => 'rescuer_field_report',
            'incident_type' => 'flood',
            'property_damage' => 'major',
            'death_count' => 1,
            'injured_count' => 1,
            'missing_count' => 0,
            'user_notes' => 'One person needs evacuation.',
            'members' => [['member_id' => 'M-1', 'status_key' => 'injured']],
        ]);
        DB::table('household_statuses')->insert(['status_id' => 1, 'status_key' => 'needs_help', 'status_label' => 'Needs help']);
        DB::table('households')->insert(['household_id' => 'HH-1', 'household_code' => 'HH-2026-0001', 'household_name' => 'Rivera Household']);
        DB::table('household_members')->insert(['member_id' => 'M-1', 'household_id' => 'HH-1', 'first_name' => 'Sam', 'last_name' => 'Rivera']);
        DB::table('member_statuses')->insert(['status_id' => 2, 'status_key' => 'injured', 'status_label' => 'Injured']);
        DB::table('household_status_logs')->insert([
            'status_log_id' => 10,
            'household_id' => 'HH-1',
            'disaster_id' => 'EV-1',
            'status_id' => 1,
            'source' => 'responder_field_report',
            'notes' => $notes,
            'submitted_at' => now(),
        ]);
        DB::table('responder_field_reports')->insert([
            'report_id' => 20,
            'status_log_id' => 10,
            'incident_type' => 'flood',
            'property_damage' => 'major',
            'death_count' => 1,
            'injured_count' => 1,
            'missing_count' => 0,
        ]);
        DB::table('responder_field_report_members')->insert([
            'report_id' => 20,
            'household_id' => 'HH-1',
            'member_id' => 'M-1',
            'status_id' => 2,
            'status_key' => 'injured',
        ]);
    }

    public function test_admin_field_report_includes_the_saved_counts_and_member_assessment(): void
    {
        $row = app(RescuerFieldReportQuery::class)
            ->paginateAdmin(['event_id' => 'EV-1'], 20)
            ->items()[0];

        $report = (new RescuerMobileFieldReportResource($row))->resolve(Request::create('/'));

        $this->assertSame(1, $report['death_count']);
        $this->assertSame(1, $report['injured_count']);
        $this->assertSame(0, $report['missing_count']);
        $this->assertSame('One person needs evacuation.', $report['notes']);
        $this->assertSame([[
            'member_id' => 'M-1',
            'name' => 'Sam Rivera',
            'status_key' => 'injured',
            'status_label' => 'Injured',
        ]], $report['members']);
    }
}
