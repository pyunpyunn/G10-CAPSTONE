<?php

namespace Tests\Feature;

use App\Presenters\ArchivePresenter;
use Tests\TestCase;

class ArchivePresenterTest extends TestCase
{
    public function test_saved_group_payload_is_presented_with_a_safe_category_and_valid_records(): void
    {
        $presenter = app(ArchivePresenter::class);
        $group = $presenter->savedGroup((object) [
            'archive_note' => json_encode(['category' => 'dispatch-logs', 'records' => [['id' => 'A-1'], ['label' => 'missing id']]]),
            'reference_table' => 'dispatch-logs',
            'reference_id' => 'AG-1',
            'archive_id' => 5,
            'archived_at' => '2026-09-29 01:02:03',
        ], ['disaster-events', 'dispatch-logs']);

        $this->assertSame('AG-1', $group['id']);
        $this->assertSame('dispatch-logs', $group['category']);
        $this->assertSame([['id' => 'A-1']], $group['records']);
        $this->assertSame('Sep 29, 2026, 1:02 AM', $group['savedAt']);
    }

    public function test_invalid_saved_group_payload_uses_default_category_and_empty_records(): void
    {
        $group = app(ArchivePresenter::class)->savedGroup((object) [
            'archive_note' => '{bad json', 'reference_table' => 'unknown', 'reference_id' => null,
            'archive_id' => 9, 'archived_at' => null,
        ], ['disaster-events']);

        $this->assertSame('9', $group['id']);
        $this->assertSame('disaster-events', $group['category']);
        $this->assertSame([], $group['records']);
    }
}


