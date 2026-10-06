<?php

namespace App\Services\Shared;

use App\Jobs\SyncTrackingAidRequest;
use App\Presenters\TrackingAidPayloadPresenter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Support\RequestSchema as Schema;
use RuntimeException;
use Throwable;

class TrackingAidForwardingService
{
    public function forwardRequest(
        object $resourceRequest,
        string $trackingReference,
        string|object|null $forwardedBy,
        ?string $validationNotes
    ): array {
        if (DB::transactionLevel() > 0) {
            SyncTrackingAidRequest::dispatch(
                (string) $resourceRequest->request_id,
                true,
                $trackingReference,
                is_string($forwardedBy) ? $forwardedBy : ($forwardedBy->user_id ?? null),
                is_object($forwardedBy) ? ($forwardedBy->name ?? trim(($forwardedBy->first_name ?? '').' '.($forwardedBy->last_name ?? ''))) : null,
                is_object($forwardedBy) ? ($forwardedBy->role?->role_key ?? $forwardedBy->role_key ?? null) : null,
                $validationNotes,
            )->onConnection('trackingaid_outbox');

            return ['tracking_reference' => $trackingReference, 'forwarded_at' => now()->toDateTimeString(), 'table' => (string) config('services.trackingaid.forward_table', 'resqperation_forwarded_requests')];
        }

        $connection = (string) config('services.trackingaid.connection', 'trackingaid');
        $table = (string) config('services.trackingaid.forward_table', 'resqperation_forwarded_requests');

        try {
            config(["database.connections.{$connection}.options." . \PDO::ATTR_TIMEOUT => 2]);

            $now = now();
            $payload = app(TrackingAidPayloadPresenter::class)->payload($resourceRequest, $trackingReference, $forwardedBy, $validationNotes, $now);

            DB::connection($connection)
                ->table($table)
                ->updateOrInsert(
                    ['tracking_reference' => $trackingReference],
                    $payload
                );

            return [
                'tracking_reference' => $trackingReference,
                'forwarded_at' => $now->toDateTimeString(),
                'table' => $table,
            ];
        } catch (Throwable $exception) {
            report($exception);

            throw new RuntimeException(
                'TrackingAid database cannot receive the request right now. Check the TrackingAid DB credentials and permissions.',
                0,
                $exception
            );
        }
    }

    public function syncRequestStatus(string $requestId, string $status, ?Carbon $updatedAt = null): void
    {
        if (static::$isUnreachable) {
            return;
        }

        if (DB::transactionLevel() > 0) {
            SyncTrackingAidRequest::dispatch($requestId)->onConnection('trackingaid_outbox');
            return;
        }

        $connection = (string) config('services.trackingaid.connection', 'trackingaid');
        $table = (string) config('services.trackingaid.forward_table', 'resqperation_forwarded_requests');

        try {
            config(["database.connections.{$connection}.options." . \PDO::ATTR_TIMEOUT => 1]);

            if (! $this->readTableAvailable($connection, $table)) {
                return;
            }

            DB::connection($connection)
                ->table($table)
                ->where('resqperation_request_id', $requestId)
                ->update([
                    'resqperation_status' => strtolower(trim($status)),
                    'updated_at' => $updatedAt ?: now(),
                ]);
        } catch (Throwable $exception) {
            static::$isUnreachable = true;
            report($exception);

            return;
        }
    }

    private static bool $isUnreachable = false;

    public function acknowledgedResourceRequests(): array
    {
        if (static::$isUnreachable) {
            return [];
        }

        $connection = (string) config('services.trackingaid.connection', 'trackingaid');
        $table = (string) config('services.trackingaid.forward_table', 'resqperation_forwarded_requests');

        try {
            config(["database.connections.{$connection}.options." . \PDO::ATTR_TIMEOUT => 1]);

            if (! $this->readTableAvailable($connection, $table)) {
                return [];
            }

            return DB::connection($connection)
                ->table($table)
                ->whereIn(DB::raw('LOWER(resqperation_status)'), ['acknowledged', 'received', 'received_by_trackingaid'])
                ->orderByDesc('updated_at')
                ->orderByDesc('forwarded_at')
                ->limit(100)
                ->get([
                    'tracking_reference',
                    'resqperation_request_id',
                    'source_system',
                    'request_source',
                    'item_name',
                    'resource_type',
                    'quantity',
                    'unit',
                    'urgency',
                    'area_label',
                    'area_note',
                    'requested_by',
                    'description',
                    'resqperation_status',
                    'forwarded_at',
                    'updated_at',
                ])
                ->map(fn (object $row): array => [
                    'tracking_reference' => $row->tracking_reference,
                    'request_id' => $row->resqperation_request_id,
                    'label' => $row->item_name ?: $row->resource_type ?: 'Resource request',
                    'source' => $row->source_system ?: $row->request_source ?: 'TrackingAid',
                    'status' => $this->statusLabel($row->resqperation_status),
                    'detail' => trim(implode(' - ', array_filter([
                        trim((string) ($row->quantity ?? '').' '.(string) ($row->unit ?? '')),
                        $row->area_label,
                        $row->area_note,
                        $row->requested_by,
                        $row->description,
                    ]))),
                    'forwarded_at' => $row->forwarded_at,
                    'updated_at' => $row->updated_at,
                ])
                ->values()
                ->all();
        } catch (Throwable $exception) {
            static::$isUnreachable = true;

            return [];
        }
    }

    public function acknowledgedRequestCount(): int
    {
        if (static::$isUnreachable) {
            return 0;
        }

        $connection = (string) config('services.trackingaid.connection', 'trackingaid');
        $table = (string) config('services.trackingaid.forward_table', 'resqperation_forwarded_requests');

        try {
            config(["database.connections.{$connection}.options." . \PDO::ATTR_TIMEOUT => 1]);

            if (! $this->readTableAvailable($connection, $table)) {
                return 0;
            }

            return DB::connection($connection)->table($table)
                ->whereIn(DB::raw('LOWER(resqperation_status)'), ['acknowledged', 'received', 'received_by_trackingaid'])
                ->count();
        } catch (Throwable $exception) {
            static::$isUnreachable = true;
            report($exception);

            return 0;
        }
    }

    /** @param array<string, string> $referencesByRequest */
    public function requestHandoffStatuses(array $referencesByRequest): array
    {
        if (static::$isUnreachable) {
            return [];
        }

        $referencesByRequest = array_filter($referencesByRequest, fn (string $reference): bool => $reference !== '');
        if ($referencesByRequest === []) {
            return [];
        }

        $connection = (string) config('services.trackingaid.connection', 'trackingaid');
        $table = (string) config('services.trackingaid.forward_table', 'resqperation_forwarded_requests');

        try {
            config(["database.connections.{$connection}.options." . \PDO::ATTR_TIMEOUT => 1]);

            if (! $this->readTableAvailable($connection, $table)) {
                return [];
            }

            $rows = DB::connection($connection)->table($table)
                ->whereIn('tracking_reference', array_values($referencesByRequest))
                ->get(['tracking_reference', 'resqperation_request_id', 'resqperation_status', 'updated_at']);

            $result = [];
            foreach ($rows as $row) {
                if (($referencesByRequest[$row->resqperation_request_id] ?? null) !== $row->tracking_reference) {
                    continue;
                }
                $result[$row->resqperation_request_id] = [
                    'tracking_reference' => $row->tracking_reference,
                    'status' => strtolower(trim((string) $row->resqperation_status)),
                    'updated_at' => $row->updated_at,
                ];
            }

            return $result;
        } catch (Throwable $exception) {
            static::$isUnreachable = true;
            report($exception);

            return [];
        }
    }

    public function requestHandoffStatus(string $requestId, ?string $trackingReference = null): ?array
    {
        if (static::$isUnreachable) {
            return null;
        }

        $connection = (string) config('services.trackingaid.connection', 'trackingaid');
        $table = (string) config('services.trackingaid.forward_table', 'resqperation_forwarded_requests');

        try {
            config(["database.connections.{$connection}.options." . \PDO::ATTR_TIMEOUT => 1]);

            if (! $this->readTableAvailable($connection, $table)) {
                return null;
            }

            $row = DB::connection($connection)
                ->table($table)
                ->where('resqperation_request_id', $requestId)
                ->when($trackingReference, fn ($query) => $query->where('tracking_reference', $trackingReference))
                ->orderByDesc('updated_at')
                ->first(['tracking_reference', 'resqperation_status', 'updated_at']);

            return $row ? [
                'tracking_reference' => $row->tracking_reference,
                'status' => strtolower(trim((string) $row->resqperation_status)),
                'updated_at' => $row->updated_at,
            ] : null;
        } catch (Throwable $exception) {
            static::$isUnreachable = true;

            return null;
        }
    }

    private function statusLabel(?string $status): string
    {
        $key = strtolower(trim((string) $status));

        return match ($key) {
            'received_by_trackingaid' => 'Received by TrackingAid',
            'acknowledged' => 'Acknowledged',
            'forwarded' => 'Forwarded',
            default => ucwords(str_replace(['_', '-'], ' ', $key ?: 'Unknown')),
        };
    }

    private function readTableAvailable(string $connection, string $table): bool
    {
        if (static::$isUnreachable) {
            return false;
        }

        $request = app()->bound('request') ? app('request') : null;
        $key = 'trackingaid_table_available:'.$connection.':'.$table;
        if ($request instanceof \Illuminate\Http\Request && $request->attributes->has($key)) {
            return (bool) $request->attributes->get($key);
        }

        try {
            config(["database.connections.{$connection}.options." . \PDO::ATTR_TIMEOUT => 1]);
            $available = Schema::connection($connection)->hasTable($table);
        } catch (Throwable $exception) {
            static::$isUnreachable = true;
            report($exception);
            $available = false;
        }

        if ($request instanceof \Illuminate\Http\Request) {
            $request->attributes->set($key, $available);
        }

        return $available;
    }


}
