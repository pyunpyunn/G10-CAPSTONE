<?php

namespace App\Services;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class EvacuationCheckInService
{
    public function store(Request $request): JsonResponse
    {
        if (! Schema::hasTable('evacuation_records')) {
            return response()->json([
                'message' => 'Evacuation records are not available in the active database.',
            ], 503);
        }

        $validated = $request->validate([
            'household_id' => ['required', 'string', 'max:255'],
            'event_id' => ['nullable', 'string', 'max:255'],
            'center_id' => ['nullable', 'string', 'max:255'],
            'evacuated_count' => ['nullable', 'integer', 'min:1', 'max:100'],
            'method' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'household_id.required' => 'Household ID is required for evacuation verification.',
        ]);

        if (Schema::hasTable('households') && ! DB::table('households')->where('household_id', $validated['household_id'])->exists()) {
            throw ValidationException::withMessages([
                'household_id' => ['Household ID was not found in the active database.'],
            ]);
        }

        $eventId = $validated['event_id'] ?: $this->activeEventId();

        if (! $eventId) {
            return response()->json([
                'message' => 'An active disaster event is required before marking a household as evacuated.',
            ], 409);
        }

        $now = now();
        $userId = $request->user()?->user_id;
        $statusId = $this->resolveStatusId('evacuated');
        $evacuationId = $this->nextId('evacuation_records', 'evacuation_id');

        DB::transaction(function () use ($validated, $eventId, $now, $userId, $statusId, $evacuationId): void {
            DB::table('evacuation_records')->insert($this->filterColumns('evacuation_records', [
                'evacuation_id' => $evacuationId,
                'event_id' => $eventId,
                'household_id' => $validated['household_id'],
                'center_id' => $validated['center_id'] ?? null,
                'household_status_id' => $statusId,
                'evacuated_count' => $validated['evacuated_count'] ?? 1,
                'method' => $validated['method'] ?? 'qr_scan',
                'verified_by' => $userId,
                'verified_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]));

            if ($statusId && Schema::hasTable('household_status_logs')) {
                $statusLogId = $this->nextId('household_status_logs', 'status_log_id');

                DB::table('household_status_logs')->insert($this->filterColumns('household_status_logs', [
                    'status_log_id' => $statusLogId,
                    'disaster_id' => $eventId,
                    'household_id' => $validated['household_id'],
                    'status_id' => $statusId,
                    'source' => 'evacuation_verification',
                    'submitted_by_user_id' => $userId,
                    'notes' => $validated['notes'] ?? 'Household marked as officially evacuated through QR verification.',
                    'submitted_at' => $now,
                    'reviewed_by_user_id' => $userId,
                    'reviewed_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }

            if (Schema::hasTable('household_disasters')) {
                $update = array_filter([
                    'current_status_id' => $statusId,
                    'last_status_source' => 'evacuation_verification',
                    'last_status_notes' => $validated['notes'] ?? 'Officially evacuated at center.',
                    'last_reported_by_user_id' => $userId,
                    'updated_at' => $now,
                ], fn ($value) => $value !== null);

                DB::table('household_disasters')
                    ->where('household_id', $validated['household_id'])
                    ->where('disaster_id', $eventId)
                    ->update($this->filterColumns('household_disasters', $update));
            }
        });

        return response()->json([
            'message' => 'Household evacuation verified and recorded.',
            'data' => [
                'evacuation_id' => $evacuationId,
                'household_id' => $validated['household_id'],
                'event_id' => $eventId,
                'status' => 'evacuated',
            ],
        ], 201);
    }

    public function verifyQr(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'qr_payload' => ['required', 'string', 'max:5000'],
            'center_id' => ['nullable', 'string', 'max:255'],
            'evacuated_count' => ['nullable', 'integer', 'min:1', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $payload = json_decode($validated['qr_payload'], true);

        if (! is_array($payload) && trim($validated['qr_payload']) !== '') {
            $payload = [
                'household_id' => trim($validated['qr_payload']),
            ];
        }

        if (! is_array($payload) || empty($payload['household_id'])) {
            throw ValidationException::withMessages([
                'qr_payload' => ['QR code payload is invalid or missing a household ID.'],
            ]);
        }

        $request->merge([
            'household_id' => $payload['household_id'],
            'event_id' => $payload['event_id'] ?? null,
            'center_id' => $validated['center_id'] ?? null,
            'evacuated_count' => $validated['evacuated_count'] ?? 1,
            'method' => 'qr_scan',
            'notes' => $validated['notes'] ?? 'Verified through household QR scan.',
        ]);

        return $this->store($request);
    }

    private function activeEventId(): ?string
    {
        if (! Schema::hasTable('disaster_events')) {
            return null;
        }

        $query = DB::table('disaster_events');

        if (Schema::hasColumn('disaster_events', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        if (Schema::hasColumn('disaster_events', 'ended_at')) {
            $query->whereNull('ended_at');
        }

        return $query->orderByDesc('started_at')->value('event_id');
    }

    private function resolveStatusId(string $statusKey): ?int
    {
        if (! Schema::hasTable('household_statuses')) {
            return null;
        }

        return DB::table('household_statuses')
            ->where('status_key', $statusKey)
            ->value('status_id');
    }

    private function nextId(string $table, string $column): string
    {
        $latest = DB::table($table)->orderByDesc($column)->value($column);

        if (! $latest) {
            return '1';
        }

        if (preg_match('/(\d+)$/', (string) $latest, $matches)) {
            $prefix = substr((string) $latest, 0, -strlen($matches[1]));

            return $prefix . ((int) $matches[1] + 1);
        }

        return (string) $latest . '-1';
    }

    private function filterColumns(string $table, array $data): array
    {
        return collect($data)
            ->filter(fn ($value, string $column): bool => Schema::hasColumn($table, $column))
            ->all();
    }
}
