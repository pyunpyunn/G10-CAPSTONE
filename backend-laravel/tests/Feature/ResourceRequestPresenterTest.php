<?php

namespace Tests\Feature;

use App\Presenters\ResourceRequestPresenter;
use Tests\TestCase;

class ResourceRequestPresenterTest extends TestCase
{
    public function test_request_mapping_keeps_source_category_quantity_and_event_contract(): void
    {
        $tracking = $this->createMock(\App\Services\Shared\TrackingAidForwardingService::class);
        $tracking->method('requestHandoffStatus')->willReturn(null);
        $presenter = new ResourceRequestPresenter($tracking);
        $row = (object) [
            'request_id' => 'RR-1', 'validation_status' => 'needs_validation', 'quantity' => 4, 'unit' => 'boxes',
            'request_source' => 'field_team', 'request_category' => 'resource', 'source_reference' => 'EV-1',
            'resource_type' => 'Water', 'item_name' => 'Bottled water', 'description' => 'For families',
            'evacuation_center_name' => 'Center A', 'evacuation_center_id' => 'EC-1', 'evacuation_center_address' => 'Sitio Uno',
            'evacuation_event_id' => 'EV-1', 'evacuation_event_name' => 'Flood', 'evacuation_event_ended_at' => null,
            'evacuation_event_deleted_at' => null, 'requested_by' => 'Alex', 'handled_by' => null, 'urgency_key' => 'high',
            'urgency_label' => 'High', 'request_status_key' => 'pending', 'request_status_label' => 'Pending',
            'validation_notes' => null, 'validated_by_user_id' => null, 'validated_at' => null, 'created_at' => null,
            'released_for_tracking_at' => null, 'tracking_reference' => null,
        ];

        $request = $presenter->format($row);
        $this->assertSame('field_team', $request['request_source']['key']);
        $this->assertSame('Field team', $request['request_source']['label']);
        $this->assertSame('Resource', $request['request_category']['label']);
        $this->assertSame('4 boxes', $request['need']['quantity_text']);
        $this->assertSame('active', $request['area']['event']['status']);
        $this->assertSame('Not forwarded', $request['handoff']['label']);
    }
}



