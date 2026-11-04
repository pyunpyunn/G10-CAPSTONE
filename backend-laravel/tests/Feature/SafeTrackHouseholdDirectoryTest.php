<?php

namespace Tests\Feature;

use App\Services\Shared\SafeTrackHouseholdDirectory;
use App\Services\Shared\SafeTrackReportClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SafeTrackHouseholdDirectoryTest extends TestCase
{
    public function test_directory_starts_empty_and_only_changes_after_sync(): void
    {
        Storage::fake('local');
        $directory = new SafeTrackHouseholdDirectory;
        $request = Request::create('/');
        $this->assertSame([], $directory->page($request)['households']['data']);
        $records = [['household_id' => 'ST-1', 'household_name' => 'Census household', 'member_count' => 2]];
        $this->assertSame(1, $directory->changes($records)['new_households']);
        $this->assertNull($directory->page($request)['synced_at']);
        $directory->sync($records);
        $this->assertSame(1, $directory->page($request)['households']['meta']['total']);
        $this->assertSame(0, $directory->changes($records)['new_households']);
        $records[0]['household_name'] = 'Updated';
        $this->assertSame(1, $directory->changes($records)['updated_households']);
        $this->assertSame('Census household', $directory->page($request)['households']['data'][0]['household']['household_name']);
    }

    public function test_failed_sync_preserves_saved_data(): void
    {
        Storage::fake('local');
        $directory = new SafeTrackHouseholdDirectory;
        $directory->sync([['household_id' => 'ST-1']]);
        try {
            $directory->sync([['household_name' => 'Missing ID']]);
            $this->fail('Invalid response must not replace the saved census.');
        } catch (\RuntimeException) {
            $this->assertSame('ST-1', $directory->saved()['records'][0]['household_id']);
        }
    }

    public function test_client_fetches_every_page_with_api_key(): void
    {
        config(['safetrack.base_url' => 'http://safetrack.test', 'safetrack.api_prefix' => 'api/v1/external', 'safetrack.api_key' => 'test-key']);
        Http::fakeSequence()->push(['data' => [['household_id' => '1']], 'meta' => ['last_page' => 2]])
            ->push(['data' => [['household_id' => '2']], 'meta' => ['last_page' => 2]]);
        $this->assertCount(2, app(SafeTrackReportClient::class)->households());
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => $request->hasHeader('X-API-KEY', 'test-key') && str_contains($request->url(), '/api/v1/external/households'));
    }
}
