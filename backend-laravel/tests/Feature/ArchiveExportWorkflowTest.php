<?php

namespace Tests\Feature;

use App\Presenters\ArchiveExportPresenter;
use App\Queries\ArchiveQuery;
use App\Services\Web\ArchiveExportWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\LazyCollection;
use Mockery;
use Tests\TestCase;

class ArchiveExportWorkflowTest extends TestCase
{
    public function test_csv_is_written_only_when_streamed_with_existing_columns_and_total_header(): void
    {
        $visited = 0;
        $rows = LazyCollection::make(function () use (&$visited) {
            foreach (['EVT-1', 'EVT-2'] as $id) {
                $visited++;
                yield ['export' => [
                    'event' => 'Flood', 'reference' => $id, 'type_weather' => 'Flood - Rain',
                    'period' => 'Ongoing', 'broadcasts' => '0 broadcasts',
                    'scope' => '1 HH in scope', 'status' => 'Active',
                ]];
            }
        });
        $query = Mockery::mock(ArchiveQuery::class);
        $query->shouldReceive('exportRows')->once()->andReturn([2, $rows]);

        $response = (new ArchiveExportWorkflow($query, new ArchiveExportPresenter()))
            ->export(Request::create('/api/v1/archive/export', 'GET', ['category' => 'disaster-events']));

        $this->assertSame(0, $visited);
        $this->assertSame('2', $response->headers->get('X-Archive-Total'));
        $this->assertStringContainsString('attachment;', $response->headers->get('Content-Disposition'));

        ob_start();
        $response->sendContent();
        $csv = ob_get_clean();

        $this->assertSame(2, $visited);
        $this->assertSame("Event,Reference,\"Disaster / weather\",\"Date declared / finished\",\"Broadcast logs\",\"Household scope\",Status\nFlood,EVT-1,\"Flood - Rain\",Ongoing,\"0 broadcasts\",\"1 HH in scope\",Active\nFlood,EVT-2,\"Flood - Rain\",Ongoing,\"0 broadcasts\",\"1 HH in scope\",Active\n", $csv);
    }
}
