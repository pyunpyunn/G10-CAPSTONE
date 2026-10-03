<?php

namespace App\Services\Mobile;

use App\Queries\RescuerFieldReportQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Support\RequestSchema as Schema;
use Illuminate\Validation\ValidationException;

class RescuerFieldReportWorkflow
{
    public function __construct(
        private \App\Services\Mobile\RescuerMobileSupport $support,
        private RescuerFieldReportQuery $query,
    ) {}

    public function fieldReports(Request $request): array
    {
        $user = $request->user();
        $responder = $this->support->responderForUser($user);
        return [
            'reports' => $this->query->fieldReports($responder?->responder_id, $user?->user_id),
            'status_options' => $this->query->statusOptions(),
        ];
    }

    public function statusOptions(): array
    {
        return $this->query->statusOptions();
    }

    public function fieldReportsAdmin(Request $request): array
    {
        $status = strtolower(trim((string) $request->query('status', 'all')));
        $statusId = trim((string) $request->query('status_id', ''));
        $eventId = trim((string) $request->query('event_id', ''));
        $activeEvent = $this->support->activeEvent();
        if ($eventId === '' && $activeEvent) $eventId = (string) ($activeEvent->event_id ?? '');

        return [
            'active_event' => $activeEvent,
            'status_options' => $this->query->statusOptions(),
            'summary' => $this->query->summary($eventId),
            'reports' => $this->query->fieldReports(null, null, ['status' => $status, 'status_id' => $statusId, 'event_id' => $eventId]),
        ];
    }

    public function store(Request $request): JsonResponse
    {
        if (! Schema::hasTable('household_status_logs')) {
            return $this->missingTableResponse('household_status_logs');
        }

        $activeEvent = $this->support->activeEvent();
        if (! $activeEvent) {
            return response()->json([
                'message' => 'Field reports can only be submitted after HQ declares an active disaster event.',
            ], 409);
        }

        $validated = $request->validate([
            'household_id' => ['required', 'string', 'max:255'],
            'household_head' => ['required', 'string', 'min:2', 'max:150'],
            'address' => ['required', 'string', 'min:3', 'max:255'],
            'status_key' => ['required', 'string', 'max:50'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'battery_level' => ['nullable', 'integer', 'min:0', 'max:100'],
            'notes' => ['required', 'string', 'min:5', 'max:1000'],
            'injured_count' => ['nullable', 'integer', 'min:0', 'max:999'],
            'death_count' => ['nullable', 'integer', 'min:0', 'max:999'],
            'property_damage' => ['nullable', 'string', 'max:255'],
            'members' => ['nullable', 'array'],
            'members.*.name' => ['nullable', 'string', 'max:150'],
            'members.*.condition' => ['nullable', 'string', 'max:255'],
        ], [
            'household_id.required' => 'Household ID is required.',
            'household_head.required' => 'Household head name is required.',
            'address.required' => 'Location, purok, or landmark is required.',
            'status_key.required' => 'Select the household status.',
            'notes.required' => 'Field notes are required.',
            'notes.min' => 'Field notes must describe the situation clearly.',
        ]);

        $this->validateMembers($validated['members'] ?? []);
        $statusId = $this->support->resolveHouseholdStatusId($validated['status_key']);
        if (! $statusId) {
            throw ValidationException::withMessages([
                'status_key' => ['Selected status is not available in the database.'],
            ]);
        }

        if (Schema::hasTable('households') && ! DB::table('households')->where('household_id', $validated['household_id'])->exists()) {
            throw ValidationException::withMessages([
                'household_id' => ['Household ID was not found in the shared database.'],
            ]);
        }

        $user = $request->user();
        $responder = $this->support->responderForUser($user);
        $eventId = (string) $activeEvent->event_id;
        $now = now();
        $notes = $this->fieldReportNotes($validated);

        $statusLogId = DB::transaction(function () use ($request, $user, $responder, $validated, $statusId, $eventId, $now, $notes): int {
            $statusLogId = $this->support->nextId('household_status_logs', 'status_log_id');
            DB::table('household_status_logs')->insert($this->support->filterColumns('household_status_logs', [
                'status_log_id' => $statusLogId,
                'disaster_id' => $eventId,
                'household_id' => $validated['household_id'],
                'status_id' => $statusId,
                'source' => 'responder_field_report',
                'submitted_by_user_id' => $user?->user_id,
                'responder_id' => $responder?->responder_id,
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'battery_level' => $validated['battery_level'] ?? null,
                'notes' => $notes,
                'submitted_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]));

            $this->saveLatestStatus($eventId, $validated['household_id'], $statusId, $validated, $notes, $user?->user_id, $responder?->responder_id, $now);
            $this->saveResponderReport($responder?->responder_id, $eventId, $validated, $notes, $now);
            $this->support->writeAuditLog($request, 'mobile_field_report', 'household_status_logs', (string) $statusLogId, $validated);

            return $statusLogId;
        });

        return response()->json([
            'message' => 'Field report submitted to HQ.',
            'data' => ['status_log_id' => $statusLogId],
        ], 201);
    }

    private function validateMembers(array $members): void
    {
        foreach ($members as $index => $member) {
            $name = trim((string) ($member['name'] ?? ''));
            $condition = trim((string) ($member['condition'] ?? ''));
            if (($name === '') !== ($condition === '')) {
                throw ValidationException::withMessages([
                    'members.'.($index + 1) => ['Enter both member name and condition, or leave both blank.'],
                ]);
            }
        }
    }

    private function fieldReportNotes(array $validated): string
    {
        $parts = [];
        foreach ([
            'household_head' => 'Household head: ',
            'address' => 'Address: ',
            'notes' => 'Notes: ',
            'injured_count' => 'Injuries: ',
            'death_count' => 'Deaths: ',
            'property_damage' => 'Property damage: ',
        ] as $key => $prefix) {
            if (array_key_exists($key, $validated) && $validated[$key] !== null && $validated[$key] !== '') {
                $parts[] = $prefix.$validated[$key];
            }
        }
        if (! empty($validated['members'])) {
            $parts[] = 'Members: '.json_encode($validated['members'], JSON_UNESCAPED_SLASHES);
        }

        return implode("\n", $parts);
    }

    private function saveResponderReport(?int $responderId, string $eventId, array $validated, string $notes, $now): void
    {
        if (! Schema::hasTable('responder_field_reports') || ! $responderId) {
            return;
        }

        DB::table('responder_field_reports')->insert($this->support->filterColumns('responder_field_reports', [
            'report_id' => (string) $this->support->nextId('responder_field_reports', 'report_id'),
            'responder_id' => $responderId,
            'disaster_id' => $eventId,
            'household_id' => $validated['household_id'],
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'notes' => $notes,
            'created_at' => $now,
        ]));
    }

    private function saveLatestStatus(string $eventId, string $householdId, int $statusId, array $validated, string $notes, ?string $userId, ?int $responderId, $now): void
    {
        if (! Schema::hasTable('household_disasters')) {
            return;
        }

        $needsDispatch = in_array($validated['status_key'], ['unsafe', 'needs_help', 'need_help', 'injured', 'missing'], true);
        $data = $this->support->filterColumns('household_disasters', [
            'current_status_id' => $statusId,
            'last_status_source' => 'responder_field_report',
            'last_status_notes' => $notes,
            'last_reported_by_user_id' => $userId,
            'last_latitude' => $validated['latitude'] ?? null,
            'last_longitude' => $validated['longitude'] ?? null,
            'last_battery_level' => $validated['battery_level'] ?? null,
            'last_reported_at' => $now,
            'priority_level' => $needsDispatch ? 'urgent' : 'monitor',
            'needs_dispatch' => $needsDispatch,
            'updated_at' => $now,
        ]);
        if (Schema::hasColumn('household_disasters', 'last_responder_id')) {
            $data['last_responder_id'] = $responderId;
        }

        $existing = DB::table('household_disasters')->where('disaster_id', $eventId)->where('household_id', $householdId)->first();
        if ($existing) {
            DB::table('household_disasters')->where('household_disaster_id', $existing->household_disaster_id)->update($data);
            return;
        }

        DB::table('household_disasters')->insert($this->support->filterColumns('household_disasters', array_merge($data, [
            'household_disaster_id' => $this->support->nextId('household_disasters', 'household_disaster_id'),
            'household_id' => $householdId,
            'disaster_id' => $eventId,
            'initial_status_id' => $statusId,
            'created_at' => $now,
        ])));
    }

    private function missingTableResponse(string $table): JsonResponse
    {
        return response()->json(['message' => 'The required '.$table.' table is not available in the current database.'], 503);
    }
}







