<?php

namespace Tests\Feature;

use App\Presenters\RescuerRadioPresenter;
use App\Queries\RescuerRadioFeedQuery;
use App\Services\Mobile\RescuerMobileSupport;
use App\Services\Mobile\RescuerRadioWorkflow;
use Illuminate\Http\Request;
use Tests\TestCase;

class RescuerRadioWorkflowTest extends TestCase
{
    public function test_radio_feed_workflow_delegates_to_the_feed_query(): void
    {
        $request = Request::create('/api/v1/rescuer/radio');
        $expected = ['channel' => 'team'];
        $feedQuery = $this->createMock(RescuerRadioFeedQuery::class);
        $feedQuery->expects($this->once())
            ->method('radioFeed')
            ->with($request)
            ->willReturn($expected);

        $workflow = new RescuerRadioWorkflow(
            $this->createMock(RescuerMobileSupport::class),
            $this->createMock(RescuerRadioPresenter::class),
            $feedQuery,
        );

        $this->assertSame($expected, $workflow->radioFeed($request));
    }

    public function test_feed_query_exposes_active_transmission_for_radio_workflows(): void
    {
        $feedQuery = new RescuerRadioFeedQuery(
            $this->createMock(RescuerMobileSupport::class),
            $this->createMock(RescuerRadioPresenter::class),
        );

        $this->assertNull($feedQuery->activeRadioTransmission(
            (object) ['responder_id' => 7],
            collect(),
        ));
    }
}