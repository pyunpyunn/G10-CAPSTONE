<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RescuerMobileFieldReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $row = $this->resource;

        return [
            'status_log_id' => $row->status_log_id,
            'household_id' => $row->household_id,
            'household_code' => $row->household_code ?? null,
            'household_head_name' => $row->household_head_name ?? 'Household',
            'reporter_name' => $row->reporter_name ?? null,
            'reporter_team_name' => $row->reporter_team_name ?? null,
            'status_id' => $row->status_id ?? null,
            'status_key' => $row->status_key ?? 'reported',
            'status_label' => $row->status_label ?? 'Reported',
            'event_id' => $row->disaster_id ?? null,
            'assignment_id' => $row->assignment_id ?? null,
            ...(property_exists($row, 'assignment_status') ? ['assignment_status' => $row->assignment_status
                ? app(\App\Presenters\RescueDispatchPresenter::class)->status($row->assignment_status)
                : ['key' => 'unlinked', 'label' => 'No linked assignment']] : []),
            'incident_type' => $row->incident_type ?? null,
            'incident_type_other' => $row->incident_type_other ?? null,
            'property_damage' => $row->property_damage ?? null,
            'death_count' => $row->death_count ?? null,
            'injured_count' => $row->injured_count ?? null,
            'latitude' => $row->latitude,
            'longitude' => $row->longitude,
            'battery_level' => $row->battery_level,
            'notes' => $this->reportNotes($row->notes),
            'submitted_at' => $row->submitted_at,
        ];
    }

    private function reportNotes(?string $storedNotes): ?string
    {
        $details = json_decode($storedNotes ?? '', true);
        if (is_array($details) && (($details['report_type'] ?? null) === 'rescuer_field_report'
            || array_key_exists('user_notes', $details))) {
            return is_string($details['user_notes'] ?? null) ? $details['user_notes'] : null;
        }

        return $storedNotes;
    }
}


