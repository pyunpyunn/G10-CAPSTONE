<?php
namespace Tests\Unit;
use App\Support\RescuerHouseholdQueue;
use PHPUnit\Framework\TestCase;
class RescuerHouseholdQueueTest extends TestCase
{
    public function test_queue_preserves_legacy_area_coverage_after_target_personalization(): void
    {
        $assignment = (object) ['dispatch_household_id' => null, 'household_id' => 'HH-1', 'route_notes' => json_encode(['household_ids' => ['HH-1', 'HH-2'], 'completed_households' => [5 => ['HH-1']]])];
        $this->assertSame(['HH-1', 'HH-2'], RescuerHouseholdQueue::ids($assignment, 5));
        $this->assertSame('HH-2', RescuerHouseholdQueue::target($assignment, 5));
    }
    public function test_explicit_empty_coverage_does_not_inherit_another_rescuers_household(): void
    {
        $assignment = (object) ['household_id' => 'HH-1', 'route_notes' => json_encode(['responder_households' => [5 => ['HH-1'], 6 => []]])];
        $this->assertNull(RescuerHouseholdQueue::target($assignment, 6));
        $this->assertSame('HH-1', RescuerHouseholdQueue::target($assignment, 5));
    }
}
