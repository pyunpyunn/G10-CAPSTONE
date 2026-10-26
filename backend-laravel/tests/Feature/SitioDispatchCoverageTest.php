<?php

namespace Tests\Feature;

use App\Actions\SelectDispatchResponders;
use App\Presenters\RescueDispatchPresenter;
use App\Queries\RescueDispatchQuery;
use App\Queries\SitioDispatchCoverageQuery;
use App\Services\Shared\BarangayProfileService;
use App\Services\Web\RescueDispatchWorkflow;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class SitioDispatchCoverageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(BarangayProfileService::class, new class extends BarangayProfileService {
            public function __construct() {}
            public function current(): array { return ['barangay_id' => 1]; }
        });
        Schema::create('sitios', function (Blueprint $t): void {
            $t->integer('sitio_id')->primary(); $t->integer('barangay_id'); $t->string('sitio_name');
        });
        Schema::create('puroks', function (Blueprint $t): void {
            $t->integer('purok_id')->primary(); $t->integer('sitio_id'); $t->string('purok_name');
        });
        Schema::create('addresses', function (Blueprint $t): void {
            $t->integer('address_id')->primary(); $t->integer('barangay_id');
            $t->integer('purok_id'); $t->integer('sitio_id');
        });
        Schema::create('households', function (Blueprint $t): void {
            $t->string('household_id')->primary(); $t->integer('address_id'); $t->timestamp('deleted_at')->nullable();
        });
        Schema::create('responder_assignments', function (Blueprint $t): void {
            $t->integer('assignment_id')->primary(); $t->string('disaster_id'); $t->string('status');
            $t->string('household_id')->nullable(); $t->text('route_notes')->nullable(); $t->string('assigned_area')->nullable();
        });
        DB::table('sitios')->insert([
            ['sitio_id' => 1, 'barangay_id' => 1, 'sitio_name' => 'Sitio A'],
            ['sitio_id' => 2, 'barangay_id' => 1, 'sitio_name' => 'Sitio B'],
        ]);
        DB::table('puroks')->insert([
            ['purok_id' => 10, 'sitio_id' => 1, 'purok_name' => 'Purok 1'],
            ['purok_id' => 20, 'sitio_id' => 2, 'purok_name' => 'Purok 1'],
        ]);
        DB::table('addresses')->insert([
            ['address_id' => 1, 'barangay_id' => 1, 'purok_id' => 10, 'sitio_id' => 1],
            ['address_id' => 2, 'barangay_id' => 1, 'purok_id' => 20, 'sitio_id' => 2],
        ]);
        DB::table('households')->insert([
            ['household_id' => 'HH-1', 'address_id' => 1],
            ['household_id' => 'HH-2', 'address_id' => 1],
            ['household_id' => 'HH-3', 'address_id' => 1],
            ['household_id' => 'HH-B', 'address_id' => 2],
        ]);
    }

    private function assignment(array $ids, string $status = 'dispatched', string $event = 'EVT-1'): array
    {
        return ['assignment_id' => 1, 'disaster_id' => $event, 'status' => ['key' => $status],
            'sitio_id' => 1, 'assigned_area' => 'Purok 1, Sitio A', 'household_ids' => $ids];
    }

    private function areas(): array
    {
        return [['sitio_id' => 1, 'households' => array_map(fn ($id) =>
            ['household_id' => $id, 'is_available_for_dispatch' => true], ['HH-1', 'HH-2', 'HH-3'])]];
    }

    public function test_overview_groups_active_assignments_without_loading_all_households_or_returning_queues(): void
    {
        $priorities = [['sitio_id'=>1,'sitio'=>'Sitio A','address_labels'=>[['label'=>'Purok 1, Sitio A']]],
            ['sitio_id'=>2,'sitio'=>'Sitio B','address_labels'=>[['label'=>'Purok 1, Sitio B']]]];
        DB::enableQueryLog(); DB::flushQueryLog();
        $rows = app(SitioDispatchCoverageQuery::class)->withActiveAssignments($priorities, [$this->assignment(['HH-1']),$this->assignment(['HH-2'],'completed')]);
        $this->assertCount(1,$rows[0]['activeDispatches']);
        $this->assertSame([], $rows[1]['activeDispatches']);
        $this->assertArrayNotHasKey('household_ids',$rows[0]['activeDispatches'][0]);
        $this->assertCount(0,DB::getQueryLog());
        $legacy = $this->assignment([]); unset($legacy['sitio_id']);
        $legacy['responder_households'] = [5=>['HH-B']];
        $rows = app(SitioDispatchCoverageQuery::class)->withActiveAssignments($priorities,[$legacy]);
        $this->assertSame([], $rows[0]['activeDispatches']);
        $this->assertCount(1,$rows[1]['activeDispatches']);
    }

    public function test_partial_coverage_offers_another_team_only_for_uncovered_households(): void
    {
        $rows = app(SitioDispatchCoverageQuery::class)->forEvent('EVT-1', $this->areas(), [$this->assignment(['HH-1'])]);
        $this->assertTrue($rows[0]['is_assigned']);
        $this->assertSame(1, $rows[0]['covered_households']);
        $this->assertSame(2, $rows[0]['uncovered_households']);
        $this->assertSame(['HH-2', 'HH-3'], $rows[0]['dispatchable_household_ids']);
        $this->assertTrue($rows[0]['can_add_team']);
        $this->assertFalse($rows[1]['is_assigned']);
    }

    public function test_full_coverage_blocks_another_team_and_deduplicates_households(): void
    {
        $a = $this->assignment(['HH-1', 'HH-2', 'HH-3']);
        $rows = app(SitioDispatchCoverageQuery::class)->forEvent('EVT-1', $this->areas(), [$a, $a]);
        $this->assertSame(3, $rows[0]['covered_households']);
        $this->assertSame(0, $rows[0]['uncovered_households']);
        $this->assertSame([], $rows[0]['dispatchable_household_ids']);
        $this->assertFalse($rows[0]['can_add_team']);
    }

    public function test_closed_and_previous_event_assignments_do_not_cover_current_households(): void
    {
        $rows = app(SitioDispatchCoverageQuery::class)->forEvent('EVT-1', $this->areas(), [
            $this->assignment(['HH-1'], 'completed'), $this->assignment(['HH-2'], 'cancelled'),
            $this->assignment(['HH-3'], 'dispatched', 'EVT-old'),
        ]);
        $this->assertFalse($rows[0]['is_assigned']);
        $this->assertSame(3, $rows[0]['dispatchable_households']);
        $this->assertSame([], app(SitioDispatchCoverageQuery::class)->forEvent(null, $this->areas(), []));
    }

    public function test_unknown_legacy_coverage_cannot_offer_an_additional_team(): void
    {
        $rows = app(SitioDispatchCoverageQuery::class)->forEvent('EVT-1', $this->areas(), [$this->assignment([])]);
        $this->assertTrue($rows[0]['is_assigned']);
        $this->assertFalse($rows[0]['coverage_verified']);
        $this->assertFalse($rows[0]['can_add_team']);
        $this->assertSame([], $rows[0]['dispatchable_household_ids']);
    }

    public function test_save_rejects_a_household_assigned_after_the_coverage_check(): void
    {
        DB::table('responder_assignments')->insert(['assignment_id' => 1, 'disaster_id' => 'EVT-1',
            'status' => 'dispatched', 'route_notes' => json_encode(['household_ids' => ['HH-1']])]);
        $query = Mockery::mock(RescueDispatchQuery::class);
        $query->shouldReceive('riskAreas')->once()->andReturn(collect([[
            'sitio_id' => 1, 'purok_id' => 10, 'area_name' => 'Purok 1, Sitio A',
            'households' => [['household_id' => 'HH-1', 'is_available_for_dispatch' => true]],
        ]]));
        $selector = Mockery::mock(SelectDispatchResponders::class);
        $selector->shouldNotReceive('select');
        $workflow = new RescueDispatchWorkflow($query, new RescueDispatchPresenter(), $selector);
        $this->expectException(ValidationException::class);
        $workflow->store(Request::create('/', 'POST'), ['sitio_id' => 1, 'purok_id' => 10,
            'assigned_area' => 'Purok 1, Sitio A', 'household_ids' => ['HH-1'], 'households_to_cover' => 1], (object) ['event_id' => 'EVT-1']);
    }

    public function test_save_blocks_additional_teams_when_existing_coverage_is_unknown(): void
    {
        DB::table('responder_assignments')->insert(['assignment_id' => 1, 'disaster_id' => 'EVT-1',
            'status' => 'dispatched', 'assigned_area' => 'Purok 1, Sitio A', 'route_notes' => json_encode(['sitio_id' => 1])]);
        $query = Mockery::mock(RescueDispatchQuery::class);
        $query->shouldReceive('riskAreas')->once()->andReturn(collect([[
            'sitio_id' => 1, 'purok_id' => 10, 'area_name' => 'Purok 1, Sitio A',
            'households' => [['household_id' => 'HH-2', 'is_available_for_dispatch' => true]],
        ]]));
        $selector = Mockery::mock(SelectDispatchResponders::class);
        $selector->shouldNotReceive('select');
        $workflow = new RescueDispatchWorkflow($query, new RescueDispatchPresenter(), $selector);
        $this->expectException(ValidationException::class);
        $workflow->store(Request::create('/', 'POST'), ['sitio_id' => 1, 'purok_id' => 10,
            'assigned_area' => 'Purok 1, Sitio A', 'household_ids' => ['HH-2'], 'households_to_cover' => 1], (object) ['event_id' => 'EVT-1']);
    }

    public function test_existing_team_does_not_block_saving_other_household_coverage_in_the_same_purok(): void
    {
        DB::table('responder_assignments')->insert(['assignment_id' => 1, 'disaster_id' => 'EVT-1',
            'status' => 'dispatched', 'route_notes' => json_encode(['household_ids' => ['HH-1']])]);
        $query = Mockery::mock(RescueDispatchQuery::class);
        $query->shouldReceive('riskAreas')->once()->andReturn(collect([[
            'sitio_id' => 1, 'purok_id' => 10, 'area_name' => 'Purok 1, Sitio A',
            'households' => [['household_id' => 'HH-2', 'is_available_for_dispatch' => true]],
        ]]));
        $selector = Mockery::mock(SelectDispatchResponders::class);
        $selector->shouldReceive('select')->once()->andThrow(new RuntimeException('Coverage accepted; responder selection reached.'));
        $workflow = new RescueDispatchWorkflow($query, new RescueDispatchPresenter(), $selector);
        $this->expectExceptionMessage('Coverage accepted; responder selection reached.');
        $workflow->store(Request::create('/', 'POST'), ['sitio_id' => 1, 'purok_id' => 10,
            'team_id' => 2, 'responder_count' => 1, 'assigned_area' => 'Purok 1, Sitio A',
            'household_ids' => ['HH-2'], 'households_to_cover' => 1], (object) ['event_id' => 'EVT-1']);
    }
}
