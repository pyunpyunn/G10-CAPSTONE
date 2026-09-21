<?php

namespace Tests\Feature;

use App\Models\Household;
use App\Repositories\HouseholdRepository;
use App\Services\HouseholdStatusService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class HouseholdMemberStatusQueryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createMemberStatusTables();
        $this->seedMemberStatusData();
    }

    public function test_member_statuses_are_loaded_per_member_for_the_active_disaster_event(): void
    {
        $service = new HouseholdStatusService(new HouseholdRepository(new Household()));
        $method = new ReflectionMethod(HouseholdStatusService::class, 'getMembers');
        $method->setAccessible(true);

        $members = $method->invoke($service, 'hh-1', 'evt-1', collect());

        $this->assertCount(2, $members);
        $this->assertSame('Safe', $members[0]['status']['label']);
        $this->assertSame('Unsafe', $members[1]['status']['label']);
    }

    protected function createMemberStatusTables(): void
    {
        Schema::dropIfExists('device_tracking_logs');
        Schema::dropIfExists('device_tokens');
        Schema::dropIfExists('member_disaster_statuses');
        Schema::dropIfExists('member_statuses');
        Schema::dropIfExists('relationships');
        Schema::dropIfExists('genders');
        Schema::dropIfExists('household_members');

        Schema::create('genders', function ($table) {
            $table->unsignedBigInteger('gender_id')->primary();
            $table->string('gender_label');
        });

        Schema::create('relationships', function ($table) {
            $table->unsignedBigInteger('relationship_id')->primary();
            $table->string('relationship_label');
        });

        Schema::create('household_members', function ($table) {
            $table->unsignedBigInteger('member_id')->primary();
            $table->string('household_id');
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->date('birth_date')->nullable();
            $table->unsignedBigInteger('gender_id')->nullable();
            $table->unsignedBigInteger('relationship_id')->nullable();
            $table->boolean('is_household_head')->default(false);
            $table->boolean('is_pwd')->default(false);
            $table->boolean('is_senior')->default(false);
            $table->boolean('is_pregnant')->default(false);
            $table->timestamp('deleted_at')->nullable();
        });

        Schema::create('member_statuses', function ($table) {
            $table->unsignedBigInteger('status_id')->primary();
            $table->string('status_key');
            $table->string('status_label');
            $table->string('color_hex')->nullable();
        });

        Schema::create('member_disaster_statuses', function ($table) {
            $table->unsignedBigInteger('member_status_id')->primary();
            $table->string('disaster_id');
            $table->string('household_id');
            $table->unsignedBigInteger('member_id');
            $table->unsignedBigInteger('status_id');
            $table->timestamp('updated_at')->nullable();
        });

        Schema::create('device_tokens', function ($table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('household_id');
            $table->unsignedBigInteger('member_id')->nullable();
            $table->string('device_name')->nullable();
            $table->string('platform')->nullable();
            $table->string('app_role')->nullable();
            $table->integer('battery_level')->nullable();
            $table->integer('signal_strength')->nullable();
            $table->string('last_location_label')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamp('logged_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        Schema::create('device_tracking_logs', function ($table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('household_id');
            $table->unsignedBigInteger('device_token_id')->nullable();
            $table->string('location_label')->nullable();
            $table->integer('battery_level')->nullable();
            $table->integer('signal_strength')->nullable();
            $table->boolean('is_allowed_location')->default(false);
            $table->timestamp('logged_at')->nullable();
        });
    }

    protected function seedMemberStatusData(): void
    {
        DB::table('genders')->insert([
            ['gender_id' => 1, 'gender_label' => 'Female'],
            ['gender_id' => 2, 'gender_label' => 'Male'],
        ]);

        DB::table('relationships')->insert([
            ['relationship_id' => 1, 'relationship_label' => 'Self'],
            ['relationship_id' => 2, 'relationship_label' => 'Spouse'],
        ]);

        DB::table('household_members')->insert([
            ['member_id' => 11, 'household_id' => 'hh-1', 'first_name' => 'Ana', 'last_name' => 'Dela Cruz', 'gender_id' => 1, 'relationship_id' => 1, 'is_household_head' => 1, 'is_pwd' => 0, 'is_senior' => 0, 'is_pregnant' => 0],
            ['member_id' => 12, 'household_id' => 'hh-1', 'first_name' => 'Ben', 'last_name' => 'Dela Cruz', 'gender_id' => 2, 'relationship_id' => 2, 'is_household_head' => 0, 'is_pwd' => 0, 'is_senior' => 0, 'is_pregnant' => 0],
        ]);

        DB::table('member_statuses')->insert([
            ['status_id' => 1, 'status_key' => 'safe', 'status_label' => 'Safe', 'color_hex' => '#0D9488'],
            ['status_id' => 2, 'status_key' => 'unsafe', 'status_label' => 'Unsafe', 'color_hex' => '#DC2626'],
        ]);

        DB::table('member_disaster_statuses')->insert([
            ['member_status_id' => 1, 'disaster_id' => 'evt-1', 'household_id' => 'hh-1', 'member_id' => 11, 'status_id' => 1, 'updated_at' => now()],
            ['member_status_id' => 2, 'disaster_id' => 'evt-1', 'household_id' => 'hh-1', 'member_id' => 12, 'status_id' => 2, 'updated_at' => now()],
        ]);
    }
}
