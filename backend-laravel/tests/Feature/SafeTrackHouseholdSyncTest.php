<?php

namespace Tests\Feature;

use App\Services\Shared\SafeTrackHouseholdDirectory;
use App\Services\Shared\SafeTrackHouseholdSync;
use App\Services\Shared\SafeTrackReportClient;
use App\Http\Controllers\Api\HouseholdDirectoryController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SafeTrackHouseholdSyncTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Schema::create('households', function (Blueprint $table): void {
            $table->string('household_id')->primary();
            $table->string('household_name')->nullable();
            $table->integer('address_id')->nullable();
            $table->string('login_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('household_members', function (Blueprint $table): void {
            $table->string('member_id')->primary();
            $table->string('household_id');
            $table->string('first_name')->nullable();
            $table->date('birth_date');
            $table->boolean('can_report_for_household')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    private function records(): array
    {
        return [['household_id' => 'ST-1', 'household_name' => 'Updated census',
            'members' => [['member_id' => 'M-1', 'first_name' => 'Updated', 'birth_date' => '2000-01-01']]]];
    }

    public function test_sync_updates_database_and_directory_preserving_local_links_and_absent_members(): void
    {
        DB::table('households')->insert(['household_id' => 'ST-1', 'household_name' => 'Old', 'login_user_id' => 'U-1']);
        DB::table('household_members')->insert([
            ['member_id' => 'M-1', 'household_id' => 'ST-1', 'first_name' => 'Old', 'birth_date' => '2000-01-01', 'can_report_for_household' => true],
            ['member_id' => 'M-2', 'household_id' => 'ST-1', 'first_name' => 'Retained', 'birth_date' => '2001-01-01', 'can_report_for_household' => false],
        ]);
        $directory = new SafeTrackHouseholdDirectory;
        $sync = new SafeTrackHouseholdSync;
        $sync->sync($this->records(), $directory);
        $sync->sync($this->records(), $directory);
        $this->assertSame('Updated census', DB::table('households')->value('household_name'));
        $this->assertSame('U-1', DB::table('households')->value('login_user_id'));
        $this->assertSame('Updated', DB::table('household_members')->where('member_id', 'M-1')->value('first_name'));
        $this->assertEquals(1, DB::table('household_members')->where('member_id', 'M-1')->value('can_report_for_household'));
        $this->assertSame(2, DB::table('household_members')->count());
        $this->assertSame($this->records(), $directory->saved()['records']);
    }

    public function test_identity_conflict_rolls_back_database_and_retains_directory(): void
    {
        $directory = new SafeTrackHouseholdDirectory;
        $directory->sync([['household_id' => 'OLD']]);
        DB::table('household_members')->insert(['member_id' => 'M-1', 'household_id' => 'OTHER', 'birth_date' => '2000-01-01']);
        try {
            (new SafeTrackHouseholdSync)->sync($this->records(), $directory);
            $this->fail('Conflicting member identity must be rejected.');
        } catch (\RuntimeException) {
            $this->assertSame(0, DB::table('households')->count());
            $this->assertSame('OTHER', DB::table('household_members')->value('household_id'));
            $this->assertSame('OLD', $directory->saved()['records'][0]['household_id']);
        }
    }

    public function test_sync_inserts_new_household_and_member(): void
    {
        $result = (new SafeTrackHouseholdSync)->sync($this->records(), new SafeTrackHouseholdDirectory);
        $this->assertSame(1, $result['database_households']);
        $this->assertSame(1, $result['database_members']);
        $this->assertSame('ST-1', DB::table('household_members')->value('household_id'));
    }

    public function test_update_check_does_not_sync_until_post(): void
    {
        config(['safetrack.base_url' => 'http://safetrack.test', 'safetrack.api_prefix' => 'api/v1/external', 'safetrack.api_key' => 'test-key']);
        Http::fake(['*' => Http::response(['data' => $this->records(), 'meta' => ['last_page' => 1]])]);
        $controller = new HouseholdDirectoryController;
        $directory = new SafeTrackHouseholdDirectory;
        $client = new SafeTrackReportClient;
        $sync = new SafeTrackHouseholdSync;
        $response = $controller->sync(Request::create('/households/directory/updates', 'GET'), $directory, $client, $sync);
        $this->assertSame(200, $response->status());
        $this->assertSame(0, DB::table('households')->count());
        $this->assertNull($directory->saved()['synced_at']);
        $response = $controller->sync(Request::create('/households/directory/sync', 'POST'), $directory, $client, $sync);
        $this->assertSame(200, $response->status());
        $this->assertSame(1, DB::table('households')->count());
        $this->assertNotNull($directory->saved()['synced_at']);
    }

    public function test_directory_keeps_households_as_rows(): void
    {
        $directory = new SafeTrackHouseholdDirectory;
        $directory->sync([
            ['household_id' => 'ST-1', 'location' => ['sitio_id' => 1, 'sitio_name' => 'North']],
            ['household_id' => 'ST-2', 'location' => ['sitio_id' => 1, 'sitio_name' => 'North']],
            ['household_id' => 'ST-3', 'location' => ['sitio_id' => 2, 'sitio_name' => 'South']],
        ]);

        $result = $directory->page(Request::create('/households/directory', 'GET'));

        $this->assertSame(3, count($result['households']['data']));
        $this->assertSame('ST-1', $result['households']['data'][0]['household']['household_id']);
        $this->assertSame(3, $result['households']['meta']['total']);
    }

    public function test_sitio_directory_sync_replaces_only_that_sitio_snapshot(): void
    {
        $directory = new SafeTrackHouseholdDirectory;
        $directory->sync([
            ['household_id' => 'ST-1', 'household_name' => 'Old North', 'location' => ['sitio_id' => 1]],
            ['household_id' => 'ST-1-OLD', 'household_name' => 'Removed North', 'location' => ['sitio_id' => 1]],
            ['household_id' => 'ST-2', 'household_name' => 'South', 'location' => ['sitio_id' => 2]],
        ]);

        $result = $directory->syncSitio([
            ['household_id' => 'ST-1', 'household_name' => 'Updated North', 'location' => ['sitio_id' => 1]],
        ], '1');

        $records = collect($directory->saved()['records'])->keyBy('household_id');
        $this->assertSame('Updated North', $records['ST-1']['household_name']);
        $this->assertSame('South', $records['ST-2']['household_name']);
        $this->assertArrayNotHasKey('ST-1-OLD', $records);
        $this->assertSame(1, $result['updated_households']);
        $this->assertSame(1, $result['removed_households']);
    }

    public function test_sitio_sync_endpoint_only_passes_selected_sitio_records_to_sync(): void
    {
        config(['safetrack.base_url' => 'http://safetrack.test', 'safetrack.api_prefix' => 'api/v1/external', 'safetrack.api_key' => 'test-key']);
        $records = [
            ['household_id' => 'ST-1', 'location' => ['sitio_id' => 1]],
            ['household_id' => 'ST-2', 'location' => ['sitio_id' => 2]],
        ];
        Http::fake(['*' => Http::response(['data' => $records, 'meta' => ['last_page' => 1]])]);
        $directory = new SafeTrackHouseholdDirectory;
        $sync = \Mockery::mock(SafeTrackHouseholdSync::class);
        $sync->shouldReceive('sync')->once()->with([$records[0]], $directory, '1')->andReturn(['total' => 1]);

        $response = (new HouseholdDirectoryController)->sync(
            Request::create('/households/directory/sync', 'POST', ['sitio_id' => 1]),
            $directory,
            new SafeTrackReportClient,
            $sync,
        );

        $this->assertSame(200, $response->status());
        $this->assertSame(1, $response->getData(true)['data']['total']);
    }

    public function test_connection_failure_explains_network_problem_and_preserves_data(): void
    {
        config(['safetrack.base_url' => 'http://safetrack.test', 'safetrack.api_key' => 'test-key']);
        Http::fake(['*' => Http::failedConnection()]);
        $directory = new SafeTrackHouseholdDirectory;
        $directory->sync([['household_id' => 'OLD']]);
        $response = (new HouseholdDirectoryController)->sync(
            Request::create('/households/directory/sync', 'POST'), $directory,
            new SafeTrackReportClient, new SafeTrackHouseholdSync,
        );
        $this->assertSame(503, $response->status());
        $this->assertStringContainsString('Tailscale', $response->getData(true)['message']);
        $this->assertSame('OLD', $directory->saved()['records'][0]['household_id']);
        $this->assertSame(0, DB::table('households')->count());
    }

    public function test_rejected_api_key_is_reported_without_changing_data(): void
    {
        config(['safetrack.base_url' => 'http://safetrack.test', 'safetrack.api_key' => 'test-key']);
        Http::fake(['*' => Http::response([], 401)]);
        $response = (new HouseholdDirectoryController)->sync(
            Request::create('/households/directory/sync', 'POST'), new SafeTrackHouseholdDirectory,
            new SafeTrackReportClient, new SafeTrackHouseholdSync,
        );
        $this->assertSame(503, $response->status());
        $this->assertStringContainsString('rejected the configured API key', $response->getData(true)['message']);
        $this->assertSame(0, DB::table('households')->count());
    }

    public function test_address_sync_preserves_existing_purok_and_reuses_address(): void
    {
        Schema::create('barangays', function (Blueprint $table): void { $table->integer('barangay_id')->primary(); });
        Schema::create('sitios', function (Blueprint $table): void { $table->integer('sitio_id')->primary(); });
        Schema::create('addresses', function (Blueprint $table): void {
            $table->increments('address_id');
            $table->string('street')->nullable();
            $table->string('barangay_name')->nullable();
            $table->integer('barangay_id')->nullable();
            $table->integer('sitio_id')->nullable();
            $table->integer('purok_id')->nullable();
            $table->timestamps();
        });
        DB::table('barangays')->insert(['barangay_id' => 396]);
        DB::table('sitios')->insert(['sitio_id' => 1]);
        DB::table('addresses')->insert(['address_id' => 1, 'street' => 'Old street', 'purok_id' => 10]);
        DB::table('households')->insert(['household_id' => 'ST-1', 'address_id' => 1]);
        $records = $this->records();
        $records[0]['location'] = ['street' => 'Updated street', 'barangay_id' => 396, 'sitio_id' => 1];
        (new SafeTrackHouseholdSync)->sync($records, new SafeTrackHouseholdDirectory);
        $this->assertSame('Updated street', DB::table('addresses')->value('street'));
        $this->assertSame(10, DB::table('addresses')->value('purok_id'));
        $this->assertSame(1, DB::table('addresses')->count());
    }
}
