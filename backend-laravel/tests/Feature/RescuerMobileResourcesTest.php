<?php

namespace Tests\Feature;

use App\Http\Resources\RescuerMobileProfileResource;
use App\Http\Resources\RescuerMobileOverviewResource;
use App\Http\Resources\RescuerMobileAssignmentListResource;
use App\Http\Resources\RescuerMobileFieldReportsResource;
use App\Http\Resources\RescuerMobileCheckInResource;
use App\Http\Resources\RescuerMobileEvacuationCenterResource;
use App\Http\Resources\RescuerMobileResourceRequestResource;
use App\Http\Resources\RescuerMobileRouteResource;
use App\Http\Resources\RescuerMobileEventResource;
use App\Http\Resources\RescuerMobileRadioFeedResource;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Tests\TestCase;

class RescuerMobileResourcesTest extends TestCase
{
    public function test_profile_resource_preserves_the_existing_nested_profile_contract(): void
    {
        $user = new User;
        $user->setRawAttributes([
            'user_id' => 'USR-7',
            'first_name' => 'Alex',
            'last_name' => 'Rivera',
            'username' => 'alex.r',
            'name' => 'Alex Rivera',
            'email' => 'alex@example.test',
            'contact_number' => '09170000000',
        ]);
        $role = new Role;
        $role->setRawAttributes(['role_key' => 'rescuer', 'role_name' => 'Rescuer']);
        $user->setRelation('role', $role);

        $responder = (object) [
            'responder_id' => 7,
            'responder_code' => 'R-007',
            'full_name' => 'Alex Rivera',
            'title' => null,
            'contact_number' => '09170000000',
            'team_id' => null,
            'team_name' => null,
            'team_code' => null,
            'team_type' => null,
            'duty_status' => null,
            'is_deployed' => 0,
            'skills' => null,
            'blood_type' => null,
            'address' => null,
            'emergency_contact_name' => null,
            'emergency_contact_number' => null,
        ];

        $response = (new RescuerMobileProfileResource([
            'user' => $user,
            'responder' => $responder,
            'active_assignment' => null,
        ]))->response(Request::create('/api/v1/rescuer/profile'));

        $this->assertSame([
            'data' => [
                'user' => [
                    'user_id' => 'USR-7',
                    'full_name' => 'Alex Rivera',
                    'first_name' => 'Alex',
                    'last_name' => 'Rivera',
                    'username' => 'alex.r',
                    'display_username' => 'alex.r',
                    'email' => 'alex@example.test',
                    'contact_number' => '09170000000',
                    'role' => ['role_key' => 'rescuer', 'role_name' => 'Rescuer'],
                ],
                'responder' => [
                    'responder_id' => 7,
                    'account_id' => 'R-007',
                    'responder_code' => 'R-007',
                    'full_name' => 'Alex Rivera',
                    'title' => 'Responder',
                    'contact_number' => '09170000000',
                    'team_id' => null,
                    'team_name' => 'Unassigned',
                    'team_code' => null,
                    'team_type' => null,
                'duty_status' => 'available',
                    'is_deployed' => false,
                    'skills' => null,
                    'blood_type' => 'Unknown',
                    'address' => null,
                    'emergency_contact_name' => null,
                    'emergency_contact_number' => null,
                ],
                'active_assignment' => null,
            ],
        ], $response->getData(true));
    }

    public function test_profile_update_resource_keeps_message_and_data_envelope(): void
    {
        $response = (new RescuerMobileProfileResource([
            'message' => 'Profile updated.',
            'data' => [
                'user' => null,
                'responder' => null,
                'active_assignment' => null,
            ],
        ]))->response(Request::create('/api/v1/rescuer/profile', 'PATCH'));

        $this->assertSame([
            'data' => [
                'user' => null,
                'responder' => null,
                'active_assignment' => null,
            ],
            'message' => 'Profile updated.',
        ], $response->getData(true));
    }

    public function test_overview_resource_preserves_all_top_level_mobile_sections(): void
    {
        $overview = [
            'profile' => ['user' => null, 'responder' => null],
            'active_event' => null,
            'summary' => ['total_assignments' => 0, 'active_assignments' => 0, 'completed_assignments' => 0, 'urgent_assignments' => 0],
            'assignments' => [],
            'field_reports' => [],
            'check_ins' => [],
            'evacuation_centers' => [],
            'resource_requests' => [],
            'status_options' => [],
            'category_options' => [],
        ];
        $response = (new RescuerMobileOverviewResource($overview))
            ->response(Request::create('/api/v1/rescuer/overview'));

        $this->assertSame(['data' => $overview], $response->getData(true));
    }

    public function test_assignment_list_resource_preserves_assignment_fields_and_status_labels(): void
    {
        $row = (object) [
            'assignment_id' => 9,
            'assignment_code' => 'ASG-009',
            'disaster_id' => 'EVT-1',
            'event_name' => null,
            'household_id' => 'HH-2',
            'assigned_area' => 'Zone 2',
            'household_location_label' => null,
            'priority_level' => 'urgent',
            'status' => 'en-route',
            'team_name' => null,
            'team_code' => 'TEAM-1',
            'dispatch_notes' => null,
            'route_notes' => '{"households_to_cover":3}',
            'outcome_notes' => null,
            'household_latitude' => null,
            'household_longitude' => null,
            'assigned_at' => '2026-09-03 10:00:00',
            'accepted_at' => null,
            'en_route_at' => null,
            'arrived_at' => null,
            'completed_at' => null,
            'mobile_route' => null,
        ];

        $response = (new RescuerMobileAssignmentListResource(collect([$row])))
            ->response(Request::create('/api/v1/rescuer/assignments'));

        $this->assertSame([
            'data' => [
                'assignments' => [[
                    'assignment_id' => 9,
                    'assignment_code' => 'ASG-009',
                    'event_id' => 'EVT-1',
                    'event_name' => 'Active event',
                    'household_id' => 'HH-2',
                    'assigned_area' => 'Zone 2',
                    'destination_label' => null,
                    'priority_level' => 'urgent',
                    'status_key' => 'en_route',
                    'status_label' => 'En route',
                    'team_name' => 'Assigned team',
                    'team_code' => 'TEAM-1',
                    'dispatch_notes' => null,
                    'route_notes' => ['households_to_cover' => 3],
                    'outcomes' => [],
                    'households_to_cover' => 3,
                    'latitude' => null,
                    'longitude' => null,
                    'assigned_at' => '2026-09-03 10:00:00',
                    'accepted_at' => null,
                    'en_route_at' => null,
                    'arrived_at' => null,
                    'completed_at' => null,
                    'route' => null,
                ]],
            ],
        ], $response->getData(true));
    }

    public function test_field_report_collection_preserves_report_and_filter_option_keys(): void
    {
        $report = (object) [
            'status_log_id' => 12,
            'household_id' => 'HH-12',
            'household_code' => null,
            'household_head_name' => null,
            'status_id' => 2,
            'status_key' => null,
            'status_label' => null,
            'disaster_id' => 'EVT-2',
            'latitude' => '10.2',
            'longitude' => '123.8',
            'battery_level' => 45,
            'notes' => 'Needs supplies',
            'submitted_at' => '2026-09-10 10:00:00',
        ];
        $options = [['key' => 'safe', 'label' => 'Safe', 'status_id' => 1]];
        $response = (new RescuerMobileFieldReportsResource([
            'reports' => collect([$report]),
            'status_options' => $options,
        ]))->response(Request::create('/api/v1/rescuer/field-reports'));

        $this->assertSame([
            'data' => [
                'reports' => [[
                    'status_log_id' => 12,
                    'household_id' => 'HH-12',
                    'household_code' => null,
                    'household_head_name' => 'Household',
                    'status_id' => 2,
                    'status_key' => 'reported',
                    'status_label' => 'Reported',
                    'event_id' => 'EVT-2',
                    'latitude' => '10.2',
                    'longitude' => '123.8',
                    'battery_level' => 45,
                    'notes' => 'Needs supplies',
                    'submitted_at' => '2026-09-10 10:00:00',
                ]],
                'status_options' => $options,
            ],
        ], $response->getData(true));
    }

    public function test_check_in_resource_preserves_defaults_and_optional_fields(): void
    {
        $response = (new RescuerMobileCheckInResource((object) [
            'check_in_id' => 3,
            'household_id' => 'HH-3',
            'member_id' => null,
            'latitude' => null,
            'longitude' => null,
            'check_in_method' => null,
            'notes' => null,
            'checked_in_at' => '2026-09-11 10:00:00',
        ]))->response(Request::create('/api/v1/rescuer/check-ins'));

        $this->assertSame(['data' => [
            'check_in_id' => 3,
            'household_id' => 'HH-3',
            'member_id' => null,
            'latitude' => null,
            'longitude' => null,
            'check_in_method' => 'field_visit',
            'notes' => null,
            'checked_in_at' => '2026-09-11 10:00:00',
        ]], $response->getData(true));
    }

    public function test_evacuation_center_resource_preserves_numeric_casts_and_fallbacks(): void
    {
        $response = (new RescuerMobileEvacuationCenterResource((object) [
            'evacuation_center_id' => 'EC-1',
            'name' => null,
            'latitude' => '10.2',
            'longitude' => '123.8',
            'capacity' => '100',
            'current_occupancy' => '27',
            'status' => null,
        ]))->response(Request::create('/api/v1/rescuer/overview'));

        $this->assertSame(['data' => [
            'evacuation_center_id' => 'EC-1',
            'name' => 'Evacuation center',
            'latitude' => 10.2,
            'longitude' => 123.8,
            'capacity' => 100,
            'current_occupancy' => 27,
            'status' => 'active',
        ]], $response->getData(true));
    }

    public function test_resource_request_resource_preserves_display_location_and_fields(): void
    {
        $response = (new RescuerMobileResourceRequestResource((object) [
            'request_id' => 'RR-1',
            'evacuation_center_id' => null,
            'description' => "Need food\nLocation: Zone 4, Block 2",
            'resource_type' => 'food',
            'item_name' => 'Rice',
            'quantity' => 10,
            'unit' => 'sacks',
            'validation_status' => null,
            'tracking_reference' => null,
            'created_at' => '2026-09-12 10:00:00',
        ]))->response(Request::create('/api/v1/rescuer/resource-requests'));

        $this->assertSame(['data' => [
            'request_id' => 'RR-1',
            'location' => 'Zone 4, Block 2',
            'resource_type' => 'food',
            'item_name' => 'Rice',
            'quantity' => 10,
            'unit' => 'sacks',
            'description' => "Need food\nLocation: Zone 4, Block 2",
            'validation_status' => 'needs_validation',
            'tracking_reference' => null,
            'created_at' => '2026-09-12 10:00:00',
        ]], $response->getData(true));
    }

    public function test_route_resource_decodes_planned_coordinates_and_keeps_trail_order(): void
    {
        $response = (new RescuerMobileRouteResource((object) [
            'route_id' => 4,
            'route_status' => null,
            'route_name' => null,
            'estimated_distance_km' => '1.25',
            'estimated_duration_min' => 8,
            'route_polyline' => '[[10.1,123.1],[0,0],[10.2,123.2]]',
            'mobile_trail_coordinates' => collect([
                (object) ['latitude' => '10.1', 'longitude' => '123.1', 'sequence_order' => 1, 'recorded_at' => '2026-09-12 10:00:00', 'accuracy_m' => '4.2'],
            ]),
        ]))->response(Request::create('/api/v1/rescuer/assignments/4'));

        $this->assertSame(['data' => [
            'route_id' => 4,
            'route_status' => 'active',
            'route_name' => 'Road route',
            'distance_km' => '1.25',
            'duration_min' => 8,
            'coordinates' => [
                ['latitude' => 10.1, 'longitude' => 123.1],
                ['latitude' => 10.2, 'longitude' => 123.2],
            ],
            'trail_coordinates' => [[
                'latitude' => '10.1',
                'longitude' => '123.1',
                'sequence_order' => 1,
                'recorded_at' => '2026-09-12 10:00:00',
                'accuracy_m' => '4.2',
            ]],
        ]], $response->getData(true));
    }

    public function test_event_resource_preserves_event_display_fallbacks(): void
    {
        $response = (new RescuerMobileEventResource((object) [
            'event_id' => 'EVT-7',
            'name' => 'Flood response',
            'type_name' => null,
            'severity_label' => null,
            'severity_key' => null,
            'started_at' => '2026-09-13 10:00:00',
        ]))->response(Request::create('/api/v1/rescuer/overview'));

        $this->assertSame(['data' => [
            'event_id' => 'EVT-7',
            'name' => 'Flood response',
            'type' => 'Disaster event',
            'severity' => 'Monitoring',
            'severity_key' => 'medium',
            'started_at' => '2026-09-13 10:00:00',
        ]], $response->getData(true));
    }

    public function test_radio_feed_resource_preserves_pagination_and_metadata_envelope(): void
    {
        $response = (new RescuerMobileRadioFeedResource([
            'channel' => 'team',
            'active_transmission' => null,
            'team_members' => [],
            'logs' => [
                'data' => collect(),
                'current_page' => 2,
                'per_page' => 5,
                'total' => 6,
                'has_more' => true,
            ],
            'audio_note' => 'Voice clips are saved and played through the team radio feed.',
        ]))->response(Request::create('/api/v1/rescuer/radio'));

        $this->assertSame(['data' => [
            'channel' => 'team',
            'active_transmission' => null,
            'team_members' => [],
            'logs' => [
                'data' => [],
                'current_page' => 2,
                'per_page' => 5,
                'total' => 6,
                'has_more' => true,
            ],
            'audio_note' => 'Voice clips are saved and played through the team radio feed.',
        ]], $response->getData(true));
    }
}


