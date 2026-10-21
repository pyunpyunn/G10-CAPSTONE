<?php

namespace Tests\Feature;

use App\Services\Mobile\HouseholdMobileStatusWorkflow;
use App\Services\Mobile\HouseholdTrustedHouseholdWorkflow;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HouseholdMobileStatusWorkflowTest extends TestCase
{
    public function test_mobile_options_match_the_broadcast_order_labels_and_safety_basis(): void
    {
        $this->configureBroadcastStatuses();
        $options = app(\App\Queries\HouseholdMobileReadQuery::class)->statusOptions('HH-1', 'EVT-1');
        $this->assertSame(['all_clear', 'needs_help', 'unsafe', 'safe'], array_column($options, 'key'));
        $this->assertSame(['All Clear', 'Need assistance', 'Unsafe', 'Safe'], array_column($options, 'label'));
        $this->assertSame(['safe', 'unsafe', 'unsafe', 'safe'], array_column($options, 'safety_basis'));
    }

    public function test_legacy_double_encoded_broadcast_statuses_are_read_correctly(): void
    {
        $this->configureBroadcastStatuses();
        DB::table('disaster_broadcasts')->update([
            'allowed_statuses_json' => json_encode(json_encode(['all_clear', 'needs_help', 'unsafe', 'safe'])),
        ]);
        $options = app(\App\Queries\HouseholdMobileReadQuery::class)->statusOptions('HH-1', 'EVT-1');
        $this->assertSame(['all_clear', 'needs_help', 'unsafe', 'safe'], array_column($options, 'key'));
    }

    public function test_custom_safe_status_is_recorded_as_safe_and_keeps_its_selected_name(): void
    {
        $this->configureBroadcastStatuses();
        app(\App\Http\Controllers\Api\HouseholdMobileController::class)->storeStatus($this->householdRequest(['status_key' => 'all_clear']));
        $this->assertDatabaseHas('household_status_logs', ['status_id' => 1]);
        $this->assertDatabaseHas('household_disasters', ['current_status_id' => 1, 'needs_dispatch' => 0]);
        $notes = json_decode(DB::table('household_disasters')->value('last_status_notes'), true);
        $this->assertSame('all_clear', $notes['mobile_status_key']);
        $this->assertSame('All Clear', $notes['mobile_status_label']);
        $this->assertSame('safe', $notes['safety_basis']);
    }

    public function test_selected_purok_options_only_replace_the_broadcast_for_targeted_households(): void
    {
        $this->configureBroadcastStatuses();
        Schema::create('addresses', function (Blueprint $table): void {
            $table->integer('address_id')->primary();
            $table->string('purok_sitio');
        });
        Schema::create('households', function (Blueprint $table): void {
            $table->string('household_id')->primary();
            $table->integer('address_id');
        });
        DB::table('addresses')->insert([['address_id' => 1, 'purok_sitio' => 'A'], ['address_id' => 2, 'purok_sitio' => 'B']]);
        DB::table('households')->insert([['household_id' => 'HH-1', 'address_id' => 1], ['household_id' => 'HH-2', 'address_id' => 2]]);
        DB::table('disaster_broadcasts')->insert([
            'broadcast_id' => 2, 'disaster_id' => 'EVT-1', 'scope_type' => 'selected_puroks',
            'direct_impact_puroks_json' => json_encode([['name' => 'B']]),
            'allowed_statuses_json' => json_encode(['safe', 'unsafe', 'needs_help', 'all_clear']),
        ]);
        $query = app(\App\Queries\HouseholdMobileReadQuery::class);
        $this->assertSame(['all_clear', 'needs_help', 'unsafe', 'safe'], array_column($query->statusOptions('HH-1', 'EVT-1'), 'key'));
        $this->assertSame(['safe', 'unsafe', 'needs_help', 'all_clear'], array_column($query->statusOptions('HH-2', 'EVT-1'), 'key'));
    }

    public function test_recent_alerts_include_only_broadcasts_targeted_to_the_household(): void
    {
        Schema::create('addresses', function (Blueprint $table): void {
            $table->integer('address_id')->primary();
            $table->string('purok_sitio');
        });
        Schema::create('households', function (Blueprint $table): void {
            $table->string('household_id')->primary();
            $table->integer('address_id');
        });
        Schema::create('disaster_broadcasts', function (Blueprint $table): void {
            $table->integer('broadcast_id')->primary();
            $table->string('disaster_id');
            $table->string('broadcast_title');
            $table->text('message');
            $table->string('scope_type');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->json('direct_impact_puroks_json')->nullable();
            $table->text('allowed_statuses')->nullable();
        });
        DB::table('addresses')->insert([
            ['address_id' => 1, 'purok_sitio' => 'Sitio A'],
            ['address_id' => 2, 'purok_sitio' => 'Sitio B'],
        ]);
        DB::table('households')->insert([
            ['household_id' => 'HH-1', 'address_id' => 1],
            ['household_id' => 'HH-2', 'address_id' => 2],
        ]);
        DB::table('disaster_broadcasts')->insert([
            ['broadcast_id' => 1, 'disaster_id' => 'EVT-1', 'broadcast_title' => 'Barangay Alert', 'message' => 'All areas', 'scope_type' => 'barangay_wide', 'direct_impact_puroks_json' => null, 'allowed_statuses' => null, 'sent_at' => now()->subMinutes(2), 'created_at' => now()->subMinutes(2)],
            ['broadcast_id' => 2, 'disaster_id' => 'EVT-1', 'broadcast_title' => 'Sitio A Alert', 'message' => 'A only', 'scope_type' => 'selected_puroks', 'direct_impact_puroks_json' => json_encode([['name' => 'Sitio A']]), 'allowed_statuses' => null, 'sent_at' => now()->subMinute(), 'created_at' => now()->subMinute()],
            ['broadcast_id' => 3, 'disaster_id' => 'EVT-1', 'broadcast_title' => 'Sitio B Alert', 'message' => 'B only', 'scope_type' => 'selected_puroks', 'direct_impact_puroks_json' => json_encode([['name' => 'Sitio B']]), 'allowed_statuses' => null, 'sent_at' => now(), 'created_at' => now()],
        ]);

        $query = app(\App\Queries\HouseholdMobileReadQuery::class);

        $this->assertSame(['Sitio A Alert', 'Barangay Alert'], array_column($query->recentAlerts('EVT-1', 'HH-1'), 'title'));
        $this->assertSame(['Sitio B Alert', 'Barangay Alert'], array_column($query->recentAlerts('EVT-1', 'HH-2'), 'title'));
        DB::table('disaster_broadcasts')->where('broadcast_id', 2)->update([
            'allowed_statuses' => json_encode(['statuses' => ['safe'], 'duration' => '12 hours']),
        ]);
        $alerts = $query->recentAlerts('EVT-1', 'HH-1');
        $this->assertSame('12 hours', $alerts[0]['estimated_duration']);
        $this->assertSame('A only', $alerts[0]['message']);
        $this->assertNull($alerts[1]['estimated_duration']);
        DB::table('disaster_broadcasts')->where('broadcast_id', 2)->update(['allowed_statuses' => '{invalid']);
        $this->assertNull($query->recentAlerts('EVT-1', 'HH-1')[0]['estimated_duration']);

    }

    public function test_help_status_classification_controls_the_recorded_status(): void
    {
        $this->configureBroadcastStatuses();
        $workflow = app(HouseholdMobileStatusWorkflow::class);
        $workflow->storeStatus($this->householdRequest(['status_key' => 'needs_help']));
        $this->assertDatabaseHas('household_disasters', ['current_status_id' => 3, 'needs_dispatch' => 1]);
        $snapshot = json_decode(DB::table('disaster_broadcasts')->value('status_options_json'), true);
        $snapshot[1]['safety_basis'] = 'safe';
        DB::table('disaster_broadcasts')->update(['status_options_json' => json_encode($snapshot)]);
        $workflow->storeStatus($this->householdRequest(['status_key' => 'needs_help']));
        $this->assertDatabaseHas('household_disasters', ['current_status_id' => 1, 'needs_dispatch' => 0]);
    }

    private function configureBroadcastStatuses(): void
    {
        Schema::create('responder_assignments', function (Blueprint $table): void {
            $table->integer('assignment_id')->primary();
            $table->string('disaster_id');
            $table->string('household_id');
            $table->string('status');
        });
        Schema::table('household_statuses', fn (Blueprint $table) => $table->string('safety_basis')->nullable());
        DB::table('household_statuses')->where('status_key', 'safe')->update(['safety_basis' => 'safe']);
        DB::table('household_statuses')->where('status_key', 'needs_help')->update(['safety_basis' => 'unsafe']);
        DB::table('household_statuses')->insert([
            ['status_id' => 3, 'status_key' => 'unsafe', 'status_label' => 'Unsafe', 'safety_basis' => 'unsafe'],
            ['status_id' => 4, 'status_key' => 'all_clear', 'status_label' => 'All Clear', 'safety_basis' => 'safe'],
        ]);
        Schema::create('disaster_broadcasts', function (Blueprint $table): void {
            $table->integer('broadcast_id')->primary();
            $table->string('disaster_id');
            $table->string('scope_type');
            $table->text('allowed_statuses')->nullable();
            $table->text('allowed_statuses_json')->nullable();
            $table->text('direct_impact_puroks_json')->nullable();
            $table->text('status_options_json')->nullable();
        });
        DB::table('disaster_broadcasts')->insert([
            'broadcast_id' => 1, 'disaster_id' => 'EVT-1', 'scope_type' => 'barangay_wide',
            'allowed_statuses_json' => json_encode(['all_clear', 'needs_help', 'unsafe', 'safe']),
            'status_options_json' => json_encode([
                ['key' => 'all_clear', 'label' => 'All Clear', 'safety_basis' => 'safe'],
                ['key' => 'needs_help', 'label' => 'Need assistance', 'safety_basis' => 'unsafe'],
                ['key' => 'unsafe', 'label' => 'Unsafe', 'safety_basis' => 'unsafe'],
                ['key' => 'safe', 'label' => 'Safe', 'safety_basis' => 'safe'],
            ]),
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('disaster_events', function (Blueprint $table): void {
            $table->string('event_id')->primary();
            $table->string('name');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
        });
        Schema::create('household_statuses', function (Blueprint $table): void {
            $table->increments('status_id');
            $table->string('status_key');
            $table->string('status_label')->nullable();
        });
        Schema::create('household_status_logs', function (Blueprint $table): void {
            $table->increments('status_log_id');
            $table->string('disaster_id')->nullable();
            $table->string('household_id');
            $table->integer('status_id');
            $table->string('source')->nullable();
            $table->string('submitted_by_user_id')->nullable();
            $table->integer('device_token_id')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('location_label')->nullable();
            $table->decimal('location_accuracy_m', 8, 2)->nullable();
            $table->integer('battery_level')->nullable();
            $table->integer('signal_strength')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });
        Schema::create('household_disasters', function (Blueprint $table): void {
            $table->increments('household_disaster_id');
            $table->string('household_id');
            $table->string('disaster_id');
            $table->integer('initial_status_id')->nullable();
            $table->integer('current_status_id')->nullable();
            $table->string('last_status_source')->nullable();
            $table->text('last_status_notes')->nullable();
            $table->string('last_reported_by_user_id')->nullable();
            $table->integer('last_device_token_id')->nullable();
            $table->decimal('last_latitude', 10, 7)->nullable();
            $table->decimal('last_longitude', 10, 7)->nullable();
            $table->integer('last_battery_level')->nullable();
            $table->timestamp('last_reported_at')->nullable();
            $table->string('priority_level')->nullable();
            $table->boolean('needs_dispatch')->nullable();
            $table->timestamps();
        });
        DB::table('disaster_events')->insert([
            'event_id' => 'EVT-1', 'name' => 'Flood response', 'started_at' => now(), 'ended_at' => null,
        ]);
        DB::table('household_statuses')->insert([
            ['status_id' => 1, 'status_key' => 'safe', 'status_label' => 'Safe'],
            ['status_id' => 2, 'status_key' => 'needs_help', 'status_label' => 'Needs help'],
        ]);
    }

    public function test_store_status_preserves_contract_and_updates_the_active_household_status(): void
    {
        $request = $this->householdRequest([
            'status_key' => 'needs_help', 'notes' => 'Need water', 'latitude' => 10.2, 'longitude' => 123.8,
        ]);
        $response = app(HouseholdMobileStatusWorkflow::class)->storeStatus($request);

        $this->assertSame(201, $response->getStatusCode());
        $payload = $response->getData(true);
        $this->assertSame('Household status update saved.', $payload['message']);
        $this->assertSame(['status_log_id' => 1], $payload['data']);
        $this->assertDatabaseHas('household_status_logs', [
            'status_log_id' => 1, 'household_id' => 'HH-1', 'disaster_id' => 'EVT-1',
            'status_id' => 2, 'source' => 'household_mobile',
        ]);
        $this->assertDatabaseHas('household_disasters', [
            'household_id' => 'HH-1', 'disaster_id' => 'EVT-1', 'current_status_id' => 2,
            'priority_level' => 'urgent', 'needs_dispatch' => 1,
        ]);
    }

    public function test_member_status_rejects_missing_member_before_persisting(): void
    {
        Schema::create('household_members', function (Blueprint $table): void {
            $table->string('member_id')->primary();
            $table->string('household_id');
            $table->string('name');
        });

        $request = $this->householdRequest(['status_key' => 'safe']);
        $response = app(HouseholdMobileStatusWorkflow::class)->storeMemberStatus($request, 'MISSING');

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame(['message' => 'Household member was not found.'], $response->getData(true));
        $this->assertDatabaseCount('household_status_logs', 0);
    }

    public function test_trusted_member_status_is_written_to_the_linked_household(): void
    {
        Schema::create('household_members', function (Blueprint $table): void {
            $table->string('member_id')->primary();
            $table->string('household_id');
            $table->string('name');
        });
        Schema::create('trusted_households', function (Blueprint $table): void {
            $table->string('connection_id')->primary();
            $table->string('requesting_household_id');
            $table->string('trusted_household_id');
            $table->string('validation_status');
        });
        DB::table('household_members')->insert([
            'member_id' => 'MEMBER-2',
            'household_id' => 'HH-2',
            'name' => 'Trusted Member',
        ]);
        DB::table('trusted_households')->insert([
            'connection_id' => 'TH-2',
            'requesting_household_id' => 'HH-1',
            'trusted_household_id' => 'HH-2',
            'validation_status' => 'validated',
        ]);

        $response = app(HouseholdTrustedHouseholdWorkflow::class)->storeTrustedMemberStatus(
            $this->householdRequest(['status_key' => 'safe']),
            'TH-2',
            'MEMBER-2'
        );

        $this->assertSame(201, $response->getStatusCode());
        $this->assertDatabaseHas('household_status_logs', [
            'household_id' => 'HH-2',
            'submitted_by_user_id' => 'USR-1',
            'source' => 'trusted_household',
        ]);
    }

    private function householdRequest(array $input): Request
    {
        $request = Request::create('/api/v1/household/status', 'POST', $input);
        $request->setUserResolver(fn () => (object) ['household_id' => 'HH-1', 'user_id' => 'USR-1']);
        return $request;
    }
}



