<?php

namespace Tests\Feature;

use App\Actions\SelectDispatchResponders;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SelectDispatchRespondersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('responders', function (Blueprint $table): void {
            $table->integer('responder_id')->primary();
            $table->integer('team_id');
            $table->string('full_name');
            $table->string('duty_status');
            $table->boolean('is_deployed');
            $table->timestamp('last_active_at')->nullable();
        });
        Schema::create('responder_assignments', function (Blueprint $table): void {
            $table->integer('assignment_id')->primary();
            $table->integer('responder_id');
            $table->string('disaster_id');
            $table->string('status');
        });
        Schema::create('responder_location_logs', function (Blueprint $table): void {
            $table->integer('log_id')->primary();
            $table->integer('responder_id');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->timestamp('logged_at');
        });
        Schema::create('geotagged_locations', function (Blueprint $table): void {
            $table->integer('geotag_id')->primary();
            $table->string('location_label')->nullable();
            $table->string('household_id');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->timestamp('updated_at')->nullable();
        });
        Schema::create('household_statuses', function (Blueprint $table): void {
            $table->integer('status_id')->primary(); $table->string('status_key');
        });
        Schema::create('household_disasters', function (Blueprint $table): void {
            $table->string('household_id'); $table->string('disaster_id'); $table->integer('current_status_id');
        });
        DB::table('responders')->insert([
            ['responder_id' => 1, 'team_id' => 4, 'full_name' => 'Far', 'duty_status' => 'available', 'is_deployed' => 0, 'last_active_at' => now()->subHours(3)],
            ['responder_id' => 2, 'team_id' => 4, 'full_name' => 'Near', 'duty_status' => 'available', 'is_deployed' => 0, 'last_active_at' => now()->subHour()],
            ['responder_id' => 3, 'team_id' => 4, 'full_name' => 'Busy', 'duty_status' => 'available', 'is_deployed' => 1, 'last_active_at' => now()],
            ['responder_id' => 4, 'team_id' => 5, 'full_name' => 'Other team', 'duty_status' => 'available', 'is_deployed' => 0, 'last_active_at' => now()],
        ]);
    }

    public function test_quantity_is_capped_and_nearby_available_responder_is_selected_first(): void
    {
        DB::table('geotagged_locations')->insert(['geotag_id' => 1, 'household_id' => 'HH-1', 'latitude' => 10.0, 'longitude' => 123.0]);
        DB::table('responder_location_logs')->insert([
            ['log_id' => 1, 'responder_id' => 1, 'latitude' => 11.0, 'longitude' => 123.0, 'logged_at' => now()],
            ['log_id' => 2, 'responder_id' => 2, 'latitude' => 10.01, 'longitude' => 123.0, 'logged_at' => now()],
        ]);

        $selection = DB::transaction(fn () => app(SelectDispatchResponders::class)->select(4, 'EVT-1', 5, 'HH-1'));

        $this->assertSame([2, 1], $selection['ids']);
        $this->assertSame(2, $selection['available_count']);
        $this->assertSame(2, $selection['assigned_count']);
        $this->assertSame(5, $selection['requested_count']);
    }

    public function test_active_assignment_excludes_otherwise_available_responder(): void
    {
        DB::table('responder_assignments')->insert(['assignment_id' => 1, 'responder_id' => 1, 'disaster_id' => 'EVT-1', 'status' => 'dispatched']);

        $selection = DB::transaction(fn () => app(SelectDispatchResponders::class)->select(4, 'EVT-1', 2, null));

        $this->assertSame([2], $selection['ids']);
    }
}
