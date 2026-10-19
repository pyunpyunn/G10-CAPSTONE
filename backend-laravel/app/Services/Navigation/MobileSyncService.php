<?php

namespace App\Services\Navigation;

use App\Jobs\ComputeTripStats;
use App\Services\Mobile\HouseholdStatusWorkflow;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class MobileSyncService
{
    public function __construct(private RoutePrecomputeService $routes) {}

    public function sync(Request $request): array
    {
        $validated = $request->validate([
            'events' => ['required', 'array', 'min:1', 'max:50'],
            'events.*.client_event_id' => ['required', 'uuid'],
            'events.*.type' => ['required', 'string', 'in:trip.start,trip.points,trip.end,household.status'],
            'events.*.occurred_at' => ['required', 'date'],
            'events.*.payload' => ['required', 'array'],
        ]);

        $device = $request->attributes->get('mobile_device');
        $acks = [];
        foreach ($validated['events'] as $event) {
            $acks[] = $this->processEvent($request, (int) $device->id, (int) $device->team_id, $event);
        }

        DB::table('devices')->where('id', $device->id)->update(['last_sync_at' => now(), 'updated_at' => now()]);

        return ['acks' => $acks, 'server_time' => now()->toISOString()];
    }

    private function processEvent(Request $request, int $deviceId, int $teamId, array $event): array
    {
        $clientEventId = (string) $event['client_event_id'];
        $existing = DB::table('sync_events')->where('client_event_id', $clientEventId)->first();
        if ($existing) {
            return $this->duplicateAck($existing, $deviceId);
        }

        try {
            $result = DB::transaction(function () use ($request, $deviceId, $teamId, $event, $clientEventId): array {
                $existing = DB::table('sync_events')->where('client_event_id', $clientEventId)->lockForUpdate()->first();
                if ($existing) {
                    return $this->duplicateAck($existing, $deviceId);
                }

                DB::table('sync_events')->insert([
                    'client_event_id' => $clientEventId,
                    'device_id' => $deviceId,
                    'type' => $event['type'],
                    'status' => 'received',
                    'received_at' => now(),
                ]);

                $result = $this->applyEvent($request, $deviceId, $teamId, $event);
                DB::table('sync_events')->where('client_event_id', $clientEventId)->update([
                    'status' => 'processed',
                    'error_message' => null,
                    'result_json' => json_encode($result, JSON_THROW_ON_ERROR),
                ]);

                return ['client_event_id' => $clientEventId, 'status' => 'processed', 'result' => $result];
            }, 3);

            return $result;
        } catch (QueryException $exception) {
            $existing = DB::table('sync_events')->where('client_event_id', $clientEventId)->first();
            if ($existing) {
                return $this->duplicateAck($existing, $deviceId);
            }

            return $this->recordFailure($clientEventId, $deviceId, $event['type'], $exception->getMessage());
        } catch (Throwable $exception) {
            $message = $exception instanceof ValidationException
                ? collect($exception->errors())->flatten()->first()
                : $exception->getMessage();

            return $this->recordFailure($clientEventId, $deviceId, $event['type'], (string) $message);
        }
    }

    private function applyEvent(Request $request, int $deviceId, int $teamId, array $event): array
    {
        $payload = $event['payload'];

        return match ($event['type']) {
            'trip.start' => $this->startTrip($deviceId, $teamId, $payload, $event['occurred_at']),
            'trip.points' => $this->storeTripPoints($deviceId, $teamId, $payload),
            'trip.end' => $this->endTrip($deviceId, $teamId, $payload, $event['occurred_at']),
            'household.status' => $this->storeHouseholdStatus($request, $teamId, $payload),
            default => throw new RuntimeException('Unsupported sync event type.'),
        };
    }

    private function startTrip(int $deviceId, int $teamId, array $payload, string $occurredAt): array
    {
        $payload = Validator::make($payload, [
            'dispatch_id' => ['required', 'integer', 'min:1'],
            'profile' => ['required', 'in:auto,pedestrian'],
        ])->validate();

        $dispatch = DB::table('responder_assignments')->where('assignment_id', $payload['dispatch_id'])
            ->lockForUpdate()->first();
        if (! $dispatch || (int) $dispatch->team_id !== $teamId) {
            throw new RuntimeException('Dispatch is not assigned to this device team.');
        }
        if (in_array($dispatch->status, ['completed', 'cancelled'], true)) {
            throw new RuntimeException('A closed dispatch cannot start a trip.');
        }

        $activeTrip = DB::table('dispatch_trips')->where('dispatch_id', $dispatch->assignment_id)
            ->where('status', 'recording')->lockForUpdate()->first();
        if ($activeTrip) {
            throw new RuntimeException('This dispatch already has an active trip.');
        }

        $plannedDistance = $this->plannedDistance($dispatch, $teamId, $payload['profile']);
        $tripId = DB::table('dispatch_trips')->insertGetId([
            'dispatch_id' => $dispatch->assignment_id,
            'team_id' => $teamId,
            'device_id' => $deviceId,
            'profile' => $payload['profile'],
            'started_at' => date('Y-m-d H:i:s', strtotime($occurredAt)),
            'planned_distance_m' => $plannedDistance,
            'status' => 'recording',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['trip_id' => (int) $tripId];
    }

    private function storeTripPoints(int $deviceId, int $teamId, array $payload): array
    {
        $payload = Validator::make($payload, [
            'trip_id' => ['required', 'integer', 'min:1'],
            'points' => ['required', 'array', 'min:1', 'max:250'],
            'points.*.lat' => ['required', 'numeric', 'between:-90,90'],
            'points.*.lng' => ['required', 'numeric', 'between:-180,180'],
            'points.*.accuracy' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'points.*.speed' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'points.*.heading' => ['nullable', 'numeric', 'between:0,360'],
            'points.*.recorded_at' => ['required', 'date'],
        ])->validate();

        $trip = $this->ownedTrip($payload['trip_id'], $deviceId, $teamId, ['recording', 'completed']);
        $rows = array_map(fn (array $point): array => [
            'trip_id' => $trip->id,
            'lat' => $point['lat'],
            'lng' => $point['lng'],
            'accuracy' => $point['accuracy'] ?? null,
            'speed' => $point['speed'] ?? null,
            'heading' => $point['heading'] ?? null,
            'recorded_at' => date('Y-m-d H:i:s', strtotime($point['recorded_at'])),
        ], $payload['points']);

        DB::table('trip_points')->insert($rows);
        if ($trip->status === 'completed') {
            ComputeTripStats::dispatch((int) $trip->id)->onQueue('navigation')->afterCommit();
        }

        return ['trip_id' => (int) $trip->id, 'accepted_points' => count($rows)];
    }

    private function endTrip(int $deviceId, int $teamId, array $payload, string $occurredAt): array
    {
        $payload = Validator::make($payload, ['trip_id' => ['required', 'integer', 'min:1']])->validate();
        $trip = $this->ownedTrip($payload['trip_id'], $deviceId, $teamId, ['recording']);
        $endedAt = date('Y-m-d H:i:s', strtotime($occurredAt));
        DB::table('dispatch_trips')->where('id', $trip->id)->update([
            'ended_at' => $endedAt,
            'status' => 'completed',
            'updated_at' => now(),
        ]);
        ComputeTripStats::dispatch((int) $trip->id)->onQueue('navigation')->afterCommit();

        return ['trip_id' => (int) $trip->id, 'status' => 'completed'];
    }

    private function storeHouseholdStatus(Request $request, int $teamId, array $payload): array
    {
        $payload = Validator::make($payload, [
            'household_id' => ['required', 'string', 'max:255'],
            'status_id' => ['required', 'integer', 'min:1'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'location_label' => ['nullable', 'string', 'max:255'],
            'location_accuracy_m' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'battery_level' => ['nullable', 'integer', 'min:0', 'max:100'],
            'signal_strength' => ['nullable', 'integer', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'needs_dispatch' => ['nullable', 'boolean'],
        ])->validate();

        if (! $this->routes->assignedHouseholdIds($teamId)->contains((string) $payload['household_id'])) {
            throw new RuntimeException('Household is not assigned to this device team.');
        }

        $statusRequest = Request::create('/api/v1/households/'.$payload['household_id'].'/status-logs', 'POST', $payload);
        $statusRequest->setUserResolver(fn () => $request->user());
        $response = app(HouseholdStatusWorkflow::class)->storeStatusLog($statusRequest, $payload['household_id']);
        if ($response->getStatusCode() >= 400) {
            throw new RuntimeException((string) ($response->getData(true)['message'] ?? 'Household status was rejected.'));
        }

        return ['household_id' => $payload['household_id'], 'status' => 'accepted'];
    }

    private function plannedDistance(object $dispatch, int $teamId, string $profile): ?int
    {
        $notes = json_decode((string) ($dispatch->route_notes ?? ''), true) ?: [];
        $householdIds = collect(array_merge(
            $dispatch->household_id ? [$dispatch->household_id] : [],
            $notes['household_ids'] ?? [],
            ...array_values($notes['responder_households'] ?? []),
        ))->map(fn ($id): string => (string) $id)->filter()->unique();

        $distances = DB::table('cached_routes')->where('team_id', $teamId)->where('profile', $profile)
            ->where('status', 'ready')->whereIn('household_id', $householdIds->all())->pluck('distance_m');

        return $distances->isEmpty() ? null : (int) $distances->sum();
    }

    private function ownedTrip(int $tripId, int $deviceId, int $teamId, array $statuses): object
    {
        $trip = DB::table('dispatch_trips')->where('id', $tripId)->lockForUpdate()->first();
        if (! $trip || (int) $trip->device_id !== $deviceId || (int) $trip->team_id !== $teamId) {
            throw new RuntimeException('Trip does not belong to this device team.');
        }
        if (! in_array($trip->status, $statuses, true)) {
            throw new RuntimeException('Trip is not accepting this event in its current state.');
        }

        return $trip;
    }

    private function duplicateAck(object $existing, int $deviceId): array
    {
        if ((int) $existing->device_id !== $deviceId) {
            return ['client_event_id' => $existing->client_event_id, 'status' => 'failed', 'error' => 'client_event_id belongs to another device.'];
        }

        return [
            'client_event_id' => $existing->client_event_id,
            'status' => $existing->status === 'processed' ? 'processed' : 'failed',
            'duplicate' => true,
            'result' => json_decode($existing->result_json ?? 'null', true),
            'error' => $existing->error_message,
        ];
    }

    private function recordFailure(string $clientEventId, int $deviceId, string $type, string $message): array
    {
        $message = mb_substr($message, 0, 255);
        try {
            DB::table('sync_events')->insertOrIgnore([
                'client_event_id' => $clientEventId,
                'device_id' => $deviceId,
                'type' => $type,
                'status' => 'failed',
                'error_message' => $message,
                'received_at' => now(),
            ]);
        } catch (Throwable $exception) {
            Log::warning('Unable to persist failed mobile sync event.', ['client_event_id' => $clientEventId, 'error' => $exception->getMessage()]);
        }

        return ['client_event_id' => $clientEventId, 'status' => 'failed', 'error' => $message];
    }
}
