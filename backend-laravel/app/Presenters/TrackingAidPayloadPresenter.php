<?php

namespace App\Presenters;

use Illuminate\Support\Carbon;

class TrackingAidPayloadPresenter
{
    public function payload(object $resourceRequest, string $trackingReference, string|object|null $forwardedBy, ?string $validationNotes, Carbon $now): array
    {
        $quantity = max(1, (int) ($resourceRequest->quantity ?? 1));
        $forwarder = $this->forwarder($forwardedBy);
        $areaLabel = $resourceRequest->evacuation_center_name
            ?: $resourceRequest->evacuation_center_id
            ?: 'Area not recorded';

        $record = [
            'tracking_reference' => $trackingReference,
            'resqperation_request_id' => $resourceRequest->request_id,
            'source_reference' => $resourceRequest->source_reference,
            'request_source' => $resourceRequest->request_source,
            'source_system' => $this->sourceSystem($resourceRequest->request_source ?? null),
            'request_category' => $resourceRequest->request_category,
            'resource_type' => $resourceRequest->resource_type,
            'item_name' => $resourceRequest->item_name,
            'quantity' => $quantity,
            'unit' => $resourceRequest->unit,
            'urgency' => $resourceRequest->urgency_label ?? $resourceRequest->urgency_key ?? 'Medium',
            'area_label' => $areaLabel,
            'area_note' => $resourceRequest->evacuation_center_address ?: $resourceRequest->description,
            'requested_by' => $resourceRequest->requested_by,
            'description' => $resourceRequest->description,
            'validation_notes' => $validationNotes,
            'validated_by_user_id' => $forwarder['user_id'],
            'forwarded_by_user_id' => $forwarder['user_id'],
            'forwarded_by_name' => $forwarder['name'],
            'forwarded_by_role' => $forwarder['role'],
            'resqperation_status' => 'forwarded',
            'forwarded_at' => $now,
            'updated_at' => $now,
        ];

        $jsonRecord = array_merge($record, [
            'forwarded_at' => $now->toDateTimeString(),
            'updated_at' => $now->toDateTimeString(),
        ]);
        $record['payload_json'] = json_encode($jsonRecord, JSON_UNESCAPED_SLASHES);
        $record['created_at'] = $now;
        return $record;
    }

    private function forwarder(string|object|null $forwardedBy): array
    {
        if (is_string($forwardedBy)) return ['user_id' => $forwardedBy, 'name' => null, 'role' => null];
        if (! $forwardedBy) return ['user_id' => null, 'name' => null, 'role' => null];
        return [
            'user_id' => $forwardedBy->user_id ?? null,
            'name' => $forwardedBy->name
                ?? trim(($forwardedBy->first_name ?? '').' '.($forwardedBy->last_name ?? ''))
                ?: null,
            'role' => $forwardedBy->role?->role_key ?? $forwardedBy->role_key ?? null,
        ];
    }

    private function sourceSystem(?string $source): string
    {
        $key = strtolower(trim((string) $source));
        return str_contains($key, 'eva') || str_contains($key, 'shared') || str_contains($key, 'evac')
            ? 'EvaTrack' : 'ResQperation';
    }
}
