<?php

namespace App\Services;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
        $connection = (string) config('services.trackingaid.connection', 'trackingaid');
        $table = (string) config('services.trackingaid.forward_table', 'resqperation_forwarded_requests');

        try {
            $this->ensureForwardTable($connection, $table);

            $now = now();
            $payload = $this->payload($resourceRequest, $trackingReference, $forwardedBy, $validationNotes, $now);

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
        $connection = (string) config('services.trackingaid.connection', 'trackingaid');
        $table = (string) config('services.trackingaid.forward_table', 'resqperation_forwarded_requests');

        try {
            if (! Schema::connection($connection)->hasTable($table)) {
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
            report($exception);

            throw new RuntimeException(
                'TrackingAid status could not be synchronized. Check the TrackingAid database connection.',
                0,
                $exception
            );
        }
    }

    private function ensureForwardTable(string $connection, string $table): void
    {
        if (Schema::connection($connection)->hasTable($table)) {
            $this->ensureForwardColumns($connection, $table);

            return;
        }

        Schema::connection($connection)->create($table, function (Blueprint $table): void {
            $table->id();
            $table->string('tracking_reference', 120)->unique();
            $table->string('resqperation_request_id', 120)->index();
            $table->string('source_reference', 120)->nullable();
            $table->string('request_source', 80)->nullable();
            $table->string('source_system', 80)->default('RESQPERATION');
            $table->string('request_category', 80)->nullable();
            $table->string('resource_type', 150)->nullable();
            $table->string('item_name', 150)->nullable();
            $table->integer('quantity')->default(1);
            $table->string('unit', 50)->nullable();
            $table->string('urgency', 80)->nullable();
            $table->string('area_label', 255)->nullable();
            $table->text('area_note')->nullable();
            $table->string('requested_by', 255)->nullable();
            $table->text('description')->nullable();
            $table->text('validation_notes')->nullable();
            $table->string('validated_by_user_id', 120)->nullable();
            $table->string('forwarded_by_user_id', 120)->nullable();
            $table->string('forwarded_by_name', 255)->nullable();
            $table->string('forwarded_by_role', 80)->nullable();
            $table->string('resqperation_status', 80)->default('forwarded');
            $table->json('payload_json')->nullable();
            $table->timestamp('forwarded_at')->nullable();
            $table->timestamps();
        });
    }

    private function ensureForwardColumns(string $connection, string $table): void
    {
        if (! Schema::connection($connection)->hasColumn($table, 'request_source')) {
            Schema::connection($connection)->table($table, function (Blueprint $table): void {
                $table->string('request_source', 80)->nullable()->after('source_reference');
            });
        }

        if (! Schema::connection($connection)->hasColumn($table, 'forwarded_by_user_id')) {
            Schema::connection($connection)->table($table, function (Blueprint $table): void {
                $table->string('forwarded_by_user_id', 120)->nullable()->after('validated_by_user_id');
                $table->string('forwarded_by_name', 255)->nullable()->after('forwarded_by_user_id');
                $table->string('forwarded_by_role', 80)->nullable()->after('forwarded_by_name');
            });
        }
    }

    private function payload(
        object $resourceRequest,
        string $trackingReference,
        string|object|null $forwardedBy,
        ?string $validationNotes,
        Carbon $now
    ): array {
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
        if (is_string($forwardedBy)) {
            return [
                'user_id' => $forwardedBy,
                'name' => null,
                'role' => null,
            ];
        }

        if (! $forwardedBy) {
            return [
                'user_id' => null,
                'name' => null,
                'role' => null,
            ];
        }

        return [
            'user_id' => $forwardedBy->user_id ?? null,
            'name' => $forwardedBy->name
                ?? trim(($forwardedBy->first_name ?? '') . ' ' . ($forwardedBy->last_name ?? ''))
                ?: null,
            'role' => $forwardedBy->role?->role_key ?? $forwardedBy->role_key ?? null,
        ];
    }

    private function sourceSystem(?string $source): string
    {
        $key = strtolower(trim((string) $source));

        return str_contains($key, 'eva') || str_contains($key, 'shared') || str_contains($key, 'evac')
            ? 'EvaTrack'
            : 'ResQperation';
    }
}
