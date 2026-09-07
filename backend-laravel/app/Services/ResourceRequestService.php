<?php

namespace App\Services;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use RuntimeException;
use Throwable;

class ResourceRequestService
{
    private TrackingAidForwardingService $trackingAid;

    public function __construct(TrackingAidForwardingService $trackingAid)
    {
        $this->trackingAid = $trackingAid;
    }

    private const SOURCES = [
        'evatrack' => 'EvaTrack',
        'field_team' => 'Field team',
        'evacuation_site' => 'Evacuation site',
        'hq_desk' => 'HQ desk',
        'household_mobile' => 'Household mobile',
        'rescuer_mobile' => 'Rescuer mobile',
        'shared_db' => 'EvaTrack request',
    ];

    private const CATEGORIES = [
        'resource' => 'Resource',
        'personnel' => 'Personnel',
        'vehicle' => 'Vehicle / transport',
    ];

    private const VALIDATION_STATUSES = [
        'needs_validation' => 'Pending',
        'verified' => 'Approved',
        'forwarded' => 'In Progress',
        'returned' => 'Returned',
        'fulfilled' => 'Completed',
        'cancelled' => 'Cancelled',
    ];

    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $status = $this->statusKey((string) $request->query('status', 'all'));
        $statusId = trim((string) $request->query('status_id', ''));
        $source = $this->sourceKey((string) $request->query('source', 'all'));
        $category = $this->categoryKey((string) $request->query('category', 'all'));
        $purok = trim((string) $request->query('purok', 'all'));
        $eventId = trim((string) $request->query('event_id', ''));
        $coreOnly = $request->boolean('core');
        $perPage = min(50, max(6, (int) $request->query('per_page', 25)));

        $query = $this->requestQuery();

        if ($search !== '') {
            $query->where(function ($inner) use ($search): void {
                $inner->where('rr.request_id', 'like', "%{$search}%")
                    ->orWhere('rr.request_source', 'like', "%{$search}%")
                    ->orWhere('rr.source_reference', 'like', "%{$search}%")
                    ->orWhere('rr.requested_by', 'like', "%{$search}%")
                    ->orWhere('rr.resource_type', 'like', "%{$search}%")
                    ->orWhere('rr.item_name', 'like', "%{$search}%")
                    ->orWhere('rr.description', 'like', "%{$search}%")
                    ->orWhere('ec.name', 'like', "%{$search}%")
                    ->orWhere('ec.osm_address', 'like', "%{$search}%");
            });
        }

        if ($status !== 'all') {
            $query->where('rr.validation_status', $status);
        }

        if ($statusId !== '') {
            $query->where('rr.status_id', $statusId);
        }

        if ($source !== 'all') {
            $query->where('rr.request_source', $source);
        }

        if ($category !== 'all') {
            $query->where('rr.request_category', $category);
        }

        if ($purok !== '' && $purok !== 'all') {
            $query->where(function ($inner) use ($purok): void {
                $inner->where('ec.name', 'like', "%{$purok}%")
                    ->orWhere('ec.osm_address', 'like', "%{$purok}%")
                    ->orWhere('rr.description', 'like', "%{$purok}%")
                    ->orWhere('rr.evacuation_center_id', 'like', "%{$purok}%");
            });
        }

        if ($eventId !== '' && $eventId !== 'all') {
            $query->where(function ($inner) use ($eventId): void {
                $inner->where('rr.source_reference', $eventId)
                    ->orWhere('ec.current_event_id', $eventId);
            });
        }

        $paginator = $query
            ->orderByRaw("CASE WHEN rr.validation_status = 'needs_validation' THEN 0 WHEN rr.validation_status = 'verified' THEN 1 ELSE 2 END")
            ->orderByDesc('rr.created_at')
            ->paginate($perPage);

        $items = collect($paginator->items())
            ->map(fn (object $row): array => $this->formatRequest($row))
            ->values()
            ->all();

        return response()->json([
            'data' => [
                'active_event' => $coreOnly ? null : $this->formatActiveEvent($this->getActiveEvent()),
                'summary' => $coreOnly
                    ? $this->coreSummary($items, $paginator->total())
                    : $this->summary(),
                'requests' => [
                    'data' => $items,
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'last_page' => $paginator->lastPage(),
                    'total' => $paginator->total(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                ],
                // Keep the primary queue independent from the optional TrackingAid database.
                'tracking_mirror' => [],
                'options' => $coreOnly ? [] : [
                    'sources' => $this->sourceOptions(),
                    'categories' => $this->categoryOptions(),
                    'statuses' => $this->statusOptions(),
                    'urgencies' => $this->urgencyOptions(),
                    'evacuation_centers' => $this->evacuationCenters(),
                    'puroks' => $this->purokOptions(),
                ],
                'scope_note' => 'RESQPERATION validates requests only. TrackingAid owns release, delivery, and fulfillment after handoff.',
            ],
        ]);
    }

    public function show(string $requestId): JsonResponse
    {
        $resourceRequest = $this->findRequest($requestId);

        if (! $resourceRequest) {
            return response()->json([
                'message' => 'Resource request was not found.',
            ], 404);
        }

        return response()->json([
            'data' => [
                'request' => $this->formatRequest($resourceRequest, true),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateRequestPayload($request);
        $activeEvent = $this->getActiveEvent();

        $created = DB::transaction(function () use ($request, $validated, $activeEvent): array {
            $now = now();
            $requestId = $this->nextRequestId();

            DB::table('resource_requests')->insert([
                'request_id' => $requestId,
                'request_source' => $validated['request_source'],
                'source_reference' => $validated['source_reference'] ?? $activeEvent?->event_id,
                'request_category' => $validated['request_category'],
                'evacuation_center_id' => $validated['evacuation_center_id'] ?? null,
                'requested_by' => $validated['requested_by'],
                'handled_by' => $request->user()?->user_id,
                'resource_type' => $validated['resource_type'],
                'item_name' => $validated['item_name'] ?? null,
                'quantity' => (int) $validated['quantity'],
                'unit' => $validated['unit'] ?? null,
                'description' => $validated['description'] ?? null,
                'urgency_id' => (int) ($validated['urgency_id'] ?? $this->urgencyId('medium')),
                'status_id' => $this->resourceStatusId('needs_validation'),
                'validation_status' => 'needs_validation',
                'validation_notes' => null,
                'validated_by_user_id' => null,
                'validated_at' => null,
                'released_for_tracking_at' => null,
                'tracking_reference' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $createdRequest = $this->formatRequest($this->findRequest($requestId), true);
            $this->writeAuditLog($request, 'create', $requestId, null, $createdRequest);

            return $createdRequest;
        });

        return response()->json([
            'message' => 'Request saved for validation.',
            'data' => [
                'request' => $created,
            ],
        ], 201);
    }

    public function storeExternal(Request $request): JsonResponse
    {
        $authError = $this->externalAuthError($request);

        if ($authError) {
            return $authError;
        }

        if (! Schema::hasTable('resource_requests')) {
            return response()->json([
                'message' => 'RESQPERATION cannot receive requests because resource_requests is missing.',
            ], 503);
        }

        $validated = $this->validateExternalRequestPayload($request);
        $now = now();
        $source = $this->externalSourceKey($validated);
        $sourceReference = $this->externalSourceReference($validated);
        $resourceType = $this->externalResourceType($validated);
        $itemName = $validated['requested_item']
            ?? $validated['item_name']
            ?? $resourceType;
        $category = $this->externalCategoryKey($validated);
        $description = $this->externalDescription($validated);

        $saved = DB::transaction(function () use (
            $request,
            $validated,
            $now,
            $source,
            $sourceReference,
            $resourceType,
            $itemName,
            $category,
            $description
        ): array {
            $existing = DB::table('resource_requests')
                ->where('request_source', $source)
                ->where('source_reference', $sourceReference)
                ->first();

            $currentStatus = $this->statusKey($existing->validation_status ?? 'needs_validation');
            $status = in_array($currentStatus, ['forwarded', 'fulfilled', 'cancelled'], true)
                ? $currentStatus
                : 'needs_validation';
            $requestId = $existing?->request_id ?: $this->nextRequestId();
            $row = [
                'request_source' => $source,
                'source_reference' => $sourceReference,
                'request_category' => $category,
                'evacuation_center_id' => $validated['evacuation_center_id'] ?? null,
                'requested_by' => $validated['requested_by'],
                'resource_type' => $resourceType,
                'item_name' => $itemName,
                'quantity' => (int) $validated['quantity'],
                'unit' => $validated['unit'] ?? null,
                'description' => $description,
                'urgency_id' => $this->externalUrgencyId($validated['priority'] ?? $validated['urgency'] ?? null),
                'status_id' => $this->resourceStatusId($status),
                'validation_status' => $status,
                'updated_at' => $now,
            ];

            if ($existing) {
                DB::table('resource_requests')
                    ->where('request_id', $requestId)
                    ->update($row);
            } else {
                DB::table('resource_requests')->insert(array_merge($row, [
                    'request_id' => $requestId,
                    'handled_by' => null,
                    'validation_notes' => null,
                    'validated_by_user_id' => null,
                    'validated_at' => null,
                    'released_for_tracking_at' => null,
                    'tracking_reference' => null,
                    'created_at' => $this->externalRequestedAt($validated) ?: $now,
                ]));
            }

            $savedRequest = $this->formatRequest($this->findRequest($requestId), true);
            $this->writeAuditLog($request, $existing ? 'external_update' : 'external_intake', $requestId, null, $savedRequest);

            return [
                'request' => $savedRequest,
                'created' => ! $existing,
            ];
        });

        return response()->json([
            'message' => $saved['created']
                ? 'External request received for validation.'
                : 'External request updated in the validation queue.',
            'data' => [
                'request' => $saved['request'],
            ],
        ], $saved['created'] ? 201 : 200);
    }

    public function validateResource(Request $request, string $requestId): JsonResponse
    {
        $resourceRequest = $this->findRequest($requestId);

        if (! $resourceRequest) {
            return response()->json([
                'message' => 'Resource request was not found.',
            ], 404);
        }

        $validated = $this->validateDecisionPayload($request);

        $updated = DB::transaction(function () use ($request, $resourceRequest, $validated, $requestId): array {
            $now = now();
            $status = $this->statusKey($validated['validation_status']);
            $oldValues = $this->formatRequest($resourceRequest, true);

            DB::table('resource_requests')
                ->where('request_id', $requestId)
                ->update([
                    'validation_status' => $status,
                    'validation_notes' => $validated['validation_notes'] ?? null,
                    'validated_by_user_id' => $request->user()?->user_id,
                    'validated_at' => $now,
                    'handled_by' => $request->user()?->user_id,
                    'status_id' => $this->resourceStatusId($status),
                    'updated_at' => $now,
                ]);

            $this->insertValidationRecord(
                $requestId,
                $status,
                $request->user()?->user_id,
                $validated['validation_notes'] ?? null,
                $validated['missing_information'] ?? null,
                $validated['duplicate_request_id'] ?? null,
                $now
            );

            $updatedRequest = $this->formatRequest($this->findRequest($requestId), true);
            $this->writeAuditLog($request, 'validate', $requestId, $oldValues, $updatedRequest);

            return $updatedRequest;
        });

        return response()->json([
            'message' => 'Validation record saved.',
            'data' => [
                'request' => $updated,
            ],
        ]);
    }

    public function forward(Request $request, string $requestId): JsonResponse
    {
        $resourceRequest = $this->findRequest($requestId);

        if (! $resourceRequest) {
            return response()->json([
                'message' => 'Resource request was not found.',
            ], 404);
        }

        $currentStatus = $this->statusKey($resourceRequest->validation_status ?? 'needs_validation');

        if (in_array($currentStatus, ['returned', 'cancelled'], true)) {
            return response()->json([
                'message' => 'Returned or cancelled requests cannot be forwarded. Verify the request again first.',
            ], 422);
        }

        $validated = $request->validate([
            'validation_notes' => ['nullable', 'string', 'max:2000'],
            'tracking_reference' => ['nullable', 'string', 'max:120'],
        ], [
            'validation_notes.max' => 'Validation notes must be shorter.',
            'tracking_reference.max' => 'Tracking reference must be shorter.',
        ]);

        try {
            $updated = DB::transaction(function () use ($request, $resourceRequest, $validated, $requestId): array {
                $now = now();
                $oldValues = $this->formatRequest($resourceRequest, true);
                $providedReference = trim((string) ($validated['tracking_reference'] ?? ''));
                $trackingReference = $providedReference !== ''
                    ? $providedReference
                    : ($resourceRequest->tracking_reference ?: $this->nextTrackingReference());
                $notes = $validated['validation_notes']
                    ?? $resourceRequest->validation_notes
                    ?? 'Verified request forwarded to TrackingAid.';

                $this->trackingAid->forwardRequest(
                    $resourceRequest,
                    $trackingReference,
                    $request->user(),
                    $notes
                );

                DB::table('resource_requests')
                    ->where('request_id', $requestId)
                    ->update([
                        'validation_status' => 'forwarded',
                        'validation_notes' => $notes,
                        'validated_by_user_id' => $request->user()?->user_id,
                        'validated_at' => $resourceRequest->validated_at ?: $now,
                        'handled_by' => $request->user()?->user_id,
                        'status_id' => $this->resourceStatusId('forwarded'),
                        'released_for_tracking_at' => $resourceRequest->released_for_tracking_at ?: $now,
                        'tracking_reference' => $trackingReference,
                        'updated_at' => $now,
                    ]);

                $this->insertValidationRecord(
                    $requestId,
                    'forwarded',
                    $request->user()?->user_id,
                    $notes,
                    null,
                    null,
                    $now
                );

                $updatedRequest = $this->formatRequest($this->findRequest($requestId), true);
                $this->writeAuditLog($request, 'forward', $requestId, $oldValues, $updatedRequest);

                return $updatedRequest;
            });
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 502);
        }

        return response()->json([
            'message' => 'Verified request forwarded to TrackingAid handoff.',
            'data' => [
                'request' => $updated,
            ],
        ]);
    }

    public function returnRequest(Request $request, string $requestId): JsonResponse
    {
        $resourceRequest = $this->findRequest($requestId);

        if (! $resourceRequest) {
            return response()->json([
                'message' => 'Resource request was not found.',
            ], 404);
        }

        $validated = $request->validate([
            'validation_notes' => ['required', 'string', 'max:2000'],
            'missing_information' => ['nullable', 'string', 'max:2000'],
            'duplicate_request_id' => ['nullable', 'string', 'max:255'],
        ], [
            'validation_notes.required' => 'Add a clear reason before returning the request.',
            'validation_notes.max' => 'Return reason must be shorter.',
            'missing_information.max' => 'Missing information note must be shorter.',
        ]);

        $updated = DB::transaction(function () use ($request, $resourceRequest, $validated, $requestId): array {
            $now = now();
            $oldValues = $this->formatRequest($resourceRequest, true);

            DB::table('resource_requests')
                ->where('request_id', $requestId)
                ->update([
                    'validation_status' => 'returned',
                    'validation_notes' => $validated['validation_notes'],
                    'validated_by_user_id' => $request->user()?->user_id,
                    'validated_at' => $now,
                    'handled_by' => $request->user()?->user_id,
                    'status_id' => $this->resourceStatusId('returned'),
                    'updated_at' => $now,
                ]);

            $this->insertValidationRecord(
                $requestId,
                'returned',
                $request->user()?->user_id,
                $validated['validation_notes'],
                $validated['missing_information'] ?? null,
                $validated['duplicate_request_id'] ?? null,
                $now
            );

            $updatedRequest = $this->formatRequest($this->findRequest($requestId), true);
            $this->writeAuditLog($request, 'return', $requestId, $oldValues, $updatedRequest);

            return $updatedRequest;
        });

        return response()->json([
            'message' => 'Request returned for missing information or duplicate check.',
            'data' => [
                'request' => $updated,
            ],
        ]);
    }

    public function complete(Request $request, string $requestId): JsonResponse
    {
        $resourceRequest = $this->findRequest($requestId);

        if (! $resourceRequest) {
            return response()->json([
                'message' => 'Resource request was not found.',
            ], 404);
        }

        $validated = $request->validate([
            'validation_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $updated = DB::transaction(function () use ($request, $resourceRequest, $validated, $requestId): array {
            $now = now();
            $oldValues = $this->formatRequest($resourceRequest, true);

            DB::table('resource_requests')
                ->where('request_id', $requestId)
                ->update([
                    'validation_status' => 'fulfilled',
                    'validation_notes' => $validated['validation_notes'] ?? $resourceRequest->validation_notes,
                    'validated_by_user_id' => $request->user()?->user_id,
                    'validated_at' => $now,
                    'handled_by' => $request->user()?->user_id,
                    'status_id' => $this->resourceStatusId('fulfilled'),
                    'updated_at' => $now,
                ]);

            $this->insertValidationRecord(
                $requestId,
                'fulfilled',
                $request->user()?->user_id,
                $validated['validation_notes'] ?? 'Request marked completed by HQ.',
                null,
                null,
                $now
            );

            $updatedRequest = $this->formatRequest($this->findRequest($requestId), true);
            $this->writeAuditLog($request, 'complete', $requestId, $oldValues, $updatedRequest);

            return $updatedRequest;
        });

        return response()->json([
            'message' => 'Request marked as completed.',
            'data' => [
                'request' => $updated,
            ],
        ]);
    }

    private function requestQuery()
    {
        return DB::table('resource_requests as rr')
            ->leftJoin('resource_request_status as rrs', 'rrs.status_id', '=', 'rr.status_id')
            ->leftJoin('urgency_levels as ul', 'ul.urgency_id', '=', 'rr.urgency_id')
            ->leftJoin('evacuation_centers as ec', 'ec.evacuation_center_id', '=', 'rr.evacuation_center_id')
            ->leftJoin('disaster_events as ec_event', 'ec_event.event_id', '=', 'ec.current_event_id')
            ->select([
                'rr.*',
                'rrs.status_key as request_status_key',
                'rrs.status_label as request_status_label',
                'ul.urgency_key',
                'ul.urgency_label',
                'ec.name as evacuation_center_name',
                'ec.osm_address as evacuation_center_address',
                'ec.current_event_id as evacuation_event_id',
                'ec_event.name as evacuation_event_name',
                'ec_event.ended_at as evacuation_event_ended_at',
                'ec_event.deleted_at as evacuation_event_deleted_at',
            ]);
    }

    private function findRequest(string $requestId): ?object
    {
        return $this->requestQuery()
            ->where('rr.request_id', $requestId)
            ->first();
    }

    private function externalAuthError(Request $request): ?JsonResponse
    {
        $configuredKey = trim((string) config('services.external_request_intake.key', ''));

        if ($configuredKey === '') {
            return response()->json([
                'message' => 'External request intake key is not configured.',
            ], 503);
        }

        $providedKey = trim((string) $request->header('X-RESQPERATION-INTEGRATION-KEY', ''));
        $providedKey = $providedKey !== '' ? $providedKey : (string) $request->bearerToken();

        if ($providedKey === '' || ! hash_equals($configuredKey, $providedKey)) {
            return response()->json([
                'message' => 'External request intake is not authorized.',
            ], 401);
        }

        return null;
    }

    private function validateExternalRequestPayload(Request $request): array
    {
        $validated = $request->validate([
            'external_request_id' => ['nullable', 'string', 'max:120'],
            'request_id' => ['nullable', 'string', 'max:120'],
            'source_reference' => ['nullable', 'string', 'max:120'],
            'source_system' => ['nullable', 'string', 'max:80'],
            'request_source' => ['nullable', 'string', 'max:80'],
            'request_type' => ['nullable', 'string', 'max:100'],
            'request_category' => ['nullable', 'string', 'max:80'],
            'requested_item' => ['nullable', 'string', 'max:150'],
            'resource_type' => ['nullable', 'string', 'max:100'],
            'item_name' => ['nullable', 'string', 'max:150'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'unit' => ['nullable', 'string', 'max:50'],
            'priority' => ['nullable', 'string', 'max:50'],
            'urgency' => ['nullable', 'string', 'max:50'],
            'location_name' => ['nullable', 'string', 'max:255'],
            'evacuation_center_id' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'requested_by' => ['required', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'description' => ['nullable', 'string', 'max:2000'],
            'requested_at' => ['nullable', 'date'],
        ], [
            'quantity.required' => 'Quantity is required.',
            'requested_by.required' => 'Requester or contact person is required.',
        ]);

        if ($this->externalResourceType($validated) === '') {
            throw ValidationException::withMessages([
                'requested_item' => 'Request item or request type is required.',
            ]);
        }

        return $validated;
    }

    private function externalSourceKey(array $payload): string
    {
        $source = strtolower(trim((string) ($payload['request_source'] ?? $payload['source_system'] ?? 'evatrack')));
        $source = str_replace([' ', '-'], '_', $source);

        return match (true) {
            str_contains($source, 'eva') => 'evatrack',
            str_contains($source, 'evac') => 'evacuation_site',
            str_contains($source, 'rescue'), str_contains($source, 'field') => 'field_team',
            str_contains($source, 'household') => 'household_mobile',
            str_contains($source, 'hq'), str_contains($source, 'resq') => 'hq_desk',
            default => $this->sourceKey($source),
        };
    }

    private function externalSourceReference(array $payload): string
    {
        $reference = trim((string) (
            $payload['external_request_id']
            ?? $payload['request_id']
            ?? $payload['source_reference']
            ?? ''
        ));

        return $reference !== '' ? $reference : 'EXT-' . now()->format('YmdHis') . '-' . strtoupper(Str::random(4));
    }

    private function externalCategoryKey(array $payload): string
    {
        $category = strtolower(trim((string) ($payload['request_category'] ?? $payload['request_type'] ?? 'resource')));

        return match (true) {
            str_contains($category, 'person'), str_contains($category, 'responder'), str_contains($category, 'medical') => 'personnel',
            str_contains($category, 'vehicle'), str_contains($category, 'transport'), str_contains($category, 'ambulance') => 'vehicle',
            default => 'resource',
        };
    }

    private function externalResourceType(array $payload): string
    {
        return trim((string) (
            $payload['resource_type']
            ?? $payload['requested_item']
            ?? $payload['item_name']
            ?? $payload['request_type']
            ?? ''
        ));
    }

    private function externalDescription(array $payload): ?string
    {
        $parts = [];

        foreach (['description', 'notes', 'location_name', 'contact_number'] as $key) {
            $value = trim((string) ($payload[$key] ?? ''));

            if ($value !== '') {
                $label = str_replace('_', ' ', $key);
                $parts[] = ucfirst($label) . ': ' . $value;
            }
        }

        if (isset($payload['latitude'], $payload['longitude'])) {
            $parts[] = 'Coordinates: ' . $payload['latitude'] . ', ' . $payload['longitude'];
        }

        if (! empty($payload['requested_at'])) {
            $parts[] = 'Requested at source: ' . Carbon::parse($payload['requested_at'])->toDateTimeString();
        }

        return empty($parts) ? null : implode("\n", $parts);
    }

    private function externalRequestedAt(array $payload): ?Carbon
    {
        return empty($payload['requested_at'])
            ? null
            : Carbon::parse($payload['requested_at']);
    }

    private function externalUrgencyId(?string $priority): ?int
    {
        $key = strtolower(trim((string) $priority));
        $key = str_replace([' ', '-'], '_', $key);
        $key = match ($key) {
            'critical', 'urgent', 'emergency' => 'critical',
            'high' => 'high',
            'low' => 'low',
            default => 'medium',
        };

        return $this->urgencyId($key) ?: $this->urgencyId('medium');
    }

    private function validateRequestPayload(Request $request): array
    {
        $urgencyIds = DB::table('urgency_levels')->pluck('urgency_id')->map(fn ($id) => (int) $id)->all();

        return $request->validate([
            'request_source' => ['required', Rule::in(array_keys(self::SOURCES))],
            'source_reference' => ['nullable', 'string', 'max:120'],
            'request_category' => ['required', Rule::in(array_keys(self::CATEGORIES))],
            'evacuation_center_id' => ['nullable', 'string', 'max:255'],
            'requested_by' => ['required', 'string', 'max:255'],
            'resource_type' => ['required', 'string', 'max:100'],
            'item_name' => ['nullable', 'string', 'max:150'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'unit' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:2000'],
            'urgency_id' => ['nullable', 'integer', Rule::in($urgencyIds)],
        ], [
            'request_source.required' => 'Request source is required.',
            'request_source.in' => 'Select a valid request source.',
            'request_category.required' => 'Request type is required.',
            'requested_by.required' => 'Requester or contact person is required.',
            'resource_type.required' => 'Need/category is required.',
            'quantity.required' => 'Quantity is required.',
            'quantity.integer' => 'Quantity must be a whole number.',
            'quantity.min' => 'Quantity must be at least 1.',
            'urgency_id.in' => 'Select a valid urgency level.',
        ]);
    }

    private function validateDecisionPayload(Request $request): array
    {
        return $request->validate([
            'validation_status' => ['required', Rule::in(array_keys(self::VALIDATION_STATUSES))],
            'validation_notes' => ['nullable', 'string', 'max:2000'],
            'missing_information' => ['nullable', 'string', 'max:2000'],
            'duplicate_request_id' => ['nullable', 'string', 'max:255'],
        ], [
            'validation_status.required' => 'Validation decision is required.',
            'validation_status.in' => 'Select a valid validation decision.',
            'validation_notes.max' => 'Validation notes must be shorter.',
            'missing_information.max' => 'Missing information note must be shorter.',
        ]);
    }

    private function formatRequest(?object $row, bool $includeDetails = false): ?array
    {
        if (! $row) {
            return null;
        }

        $validation = $this->validationStatus($row->validation_status ?? 'needs_validation');
        $quantityText = trim((string) ($row->quantity ?? '') . ' ' . (string) ($row->unit ?? ''));
        $area = $row->evacuation_center_name ?: $row->evacuation_center_id ?: 'Area not recorded';

        $data = [
            'request_id' => $row->request_id,
            'request_source' => [
                'key' => $this->sourceKey($row->request_source ?? ''),
                'label' => $this->sourceLabel($row->request_source ?? ''),
            ],
            'source_system' => $this->sourceSystem($row->request_source ?? ''),
            'source_reference' => $row->source_reference,
            'request_category' => [
                'key' => $this->categoryKey($row->request_category ?? ''),
                'label' => $this->categoryLabel($row->request_category ?? ''),
            ],
            'need' => [
                'type' => $row->resource_type ?: $row->item_name ?: 'Request',
                'item_name' => $row->item_name,
                'quantity' => (int) ($row->quantity ?? 0),
                'unit' => $row->unit,
                'quantity_text' => $quantityText !== '' ? $quantityText : 'No quantity',
            ],
            'area' => [
                'label' => $area,
                'meta' => $row->evacuation_center_address ?: $row->description ?: 'No beneficiary note recorded',
                'evacuation_center_id' => $row->evacuation_center_id,
                'event' => [
                    'event_id' => $row->evacuation_event_id,
                    'name' => $row->evacuation_event_name,
                    'status' => $this->evacuationEventStatus(
                        $row->evacuation_event_id,
                        $row->evacuation_event_ended_at,
                        $row->evacuation_event_deleted_at
                    ),
                ],
            ],
            'requested_by' => $row->requested_by ?: 'Requester not recorded',
            'handled_by' => $row->handled_by,
            'description' => $row->description,
            'urgency' => [
                'key' => $row->urgency_key ?: 'medium',
                'label' => $row->urgency_label ?: 'Medium',
            ],
            'validation' => $validation,
            'handoff' => $this->handoffStatus($row),
            'status' => [
                'key' => $row->request_status_key ?: 'pending',
                'label' => $row->request_status_label ?: 'Pending',
            ],
            'validation_notes' => $row->validation_notes,
            'validated_by_user_id' => $row->validated_by_user_id,
            'validated_at' => $this->formatDateTime($row->validated_at),
            'created_at' => $this->formatDateTime($row->created_at),
            'created_time' => $this->formatTime($row->created_at),
            'released_for_tracking_at' => $this->formatDateTime($row->released_for_tracking_at),
            'tracking_reference' => $row->tracking_reference,
        ];

        if ($includeDetails) {
            $data['validation_history'] = $this->validationHistory($row->request_id);
        }

        return $data;
    }

    private function summary(): array
    {
        $counts = DB::table('resource_requests')
            ->select('validation_status', DB::raw('COUNT(*) as total'))
            ->groupBy('validation_status')
            ->pluck('total', 'validation_status');

        return [
            'needs_validation' => (int) ($counts['needs_validation'] ?? 0),
            'verified' => (int) (($counts['verified'] ?? 0) + ($counts['validated'] ?? 0)),
            'forwarded_today' => DB::table('resource_requests')
                ->whereDate('released_for_tracking_at', Carbon::today())
                ->count(),
            'returned' => (int) ($counts['returned'] ?? 0),
            'status_ids' => [
                'needs_validation' => $this->resourceStatusId('needs_validation'),
                'verified' => $this->resourceStatusId('verified'),
                'forwarded' => $this->resourceStatusId('forwarded'),
                'returned' => $this->resourceStatusId('returned'),
                'fulfilled' => $this->resourceStatusId('fulfilled'),
                'cancelled' => $this->resourceStatusId('cancelled'),
            ],
            'rows' => [
                [
                    'key' => 'needs_validation',
                    'label' => 'Needs validation',
                    'status_id' => $this->resourceStatusId('needs_validation'),
                    'count' => (int) ($counts['needs_validation'] ?? 0),
                ],
                [
                    'key' => 'verified',
                    'label' => 'Verified',
                    'status_id' => $this->resourceStatusId('verified'),
                    'count' => (int) (($counts['verified'] ?? 0) + ($counts['validated'] ?? 0)),
                ],
                [
                    'key' => 'forwarded',
                    'label' => 'Forwarded today',
                    'status_id' => $this->resourceStatusId('forwarded'),
                    'count' => DB::table('resource_requests')
                        ->whereDate('released_for_tracking_at', Carbon::today())
                        ->count(),
                ],
                [
                    'key' => 'returned',
                    'label' => 'Returned',
                    'status_id' => $this->resourceStatusId('returned'),
                    'count' => (int) ($counts['returned'] ?? 0),
                ],
            ],
        ];
    }

    private function coreSummary(array $items, int $total): array
    {
        $counts = collect($items)->countBy(fn (array $item): string => $item['validation']['key'] ?? 'needs_validation');

        return [
            'needs_validation' => (int) $counts->get('needs_validation', 0),
            'verified' => (int) $counts->get('verified', 0),
            'forwarded_today' => (int) $counts->get('forwarded', 0),
            'returned' => (int) $counts->get('returned', 0),
            'total' => $total,
            'rows' => [],
        ];
    }

    private function trackingMirror(): array
    {
        $trackingAidInventory = $this->trackingAidInventoryMirror();

        if ($trackingAidInventory !== null) {
            return $trackingAidInventory;
        }

        $catalog = [
            ['label' => 'Food packs', 'keywords' => ['food', 'pack', 'meal', 'rice']],
            ['label' => 'Water containers', 'keywords' => ['water', 'container']],
            ['label' => 'Medical kits', 'keywords' => ['medical', 'medic', 'first aid', 'kit']],
            ['label' => 'Life jackets', 'keywords' => ['life jacket', 'jacket', 'rescue equipment']],
            ['label' => 'Evacuation tents', 'keywords' => ['tent', 'shelter', 'camp']],
            ['label' => 'Rescue vehicle', 'keywords' => ['vehicle', 'transport', 'truck', 'ambulance']],
            ['label' => 'Medical responders', 'keywords' => ['medical responder', 'responder', 'personnel', 'medic personnel']],
            ['label' => 'Transport volunteers', 'keywords' => ['volunteer', 'driver', 'transport']],
        ];

        $openRequests = DB::table('resource_requests')
            ->whereNotIn('validation_status', ['returned', 'cancelled', 'fulfilled'])
            ->get(['resource_type', 'item_name', 'description', 'quantity', 'unit']);

        return collect($catalog)->map(function (array $item) use ($openRequests): array {
            $matches = $openRequests->filter(function (object $request) use ($item): bool {
                $text = strtolower(trim(($request->resource_type ?? '') . ' ' . ($request->item_name ?? '') . ' ' . ($request->description ?? '')));

                foreach ($item['keywords'] as $keyword) {
                    if (str_contains($text, strtolower($keyword))) {
                        return true;
                    }
                }

                return false;
            });

            $quantity = $matches->sum(fn (object $request): int => (int) ($request->quantity ?? 0));
            $unit = $matches->first()?->unit ?: 'unit(s)';
            $openCount = $matches->count();

            return [
                'label' => $item['label'],
                'source' => 'TrackingAid mirror',
                'status' => $openCount > 0 ? "{$openCount} open request(s)" : 'No open request',
                'detail' => $openCount > 0 ? "{$quantity} {$unit} requested in RESQPERATION" : 'Availability must be confirmed in TrackingAid',
            ];
        })->values()->all();
    }

    private function trackingAidInventoryMirror(): ?array
    {
        $connection = (string) config('services.trackingaid.connection', 'trackingaid');

        try {
            if (! Schema::connection($connection)->hasTable('inventory')) {
                return null;
            }

            $items = DB::connection($connection)
                ->table('inventory')
                ->whereNull('deleted_at')
                ->orderByDesc('quantity')
                ->orderBy('name')
                ->limit(12)
                ->get(['id', 'name', 'category', 'sku', 'type', 'quantity', 'expiration', 'storage_location'])
                ->map(function (object $item): array {
                    $quantity = max(0, (int) ($item->quantity ?? 0));

                    return [
                        'label' => $item->name ?: 'Unnamed resource',
                        'source' => 'TrackingAid inventory',
                        'status' => $quantity > 0 ? "{$quantity} available" : 'No stock',
                        'detail' => trim(($item->category ?: 'Uncategorized') . ' - ' . ($item->storage_location ?: 'No storage location')),
                        'sku' => $item->sku,
                        'quantity' => $quantity,
                        'type' => $item->type,
                        'expiration' => $item->expiration,
                    ];
                })
                ->values()
                ->all();

            if (empty($items)) {
                return [[
                    'label' => 'No TrackingAid inventory yet',
                    'source' => 'TrackingAid inventory',
                    'status' => '0 available',
                    'detail' => 'TrackingAid inventory table is reachable but has no resources recorded.',
                ]];
            }

            return $items;
        } catch (Throwable) {
            return null;
        }
    }

    private function validationHistory(string $requestId): array
    {
        if (! Schema::hasTable('request_validations')) {
            return [];
        }

        return DB::table('request_validations')
            ->where('request_id', $requestId)
            ->orderByDesc('created_at')
            ->limit(8)
            ->get()
            ->map(fn (object $row): array => [
                'validation_id' => $row->validation_id,
                'validation_status' => $this->validationStatus($row->validation_status),
                'validator_user_id' => $row->validator_user_id,
                'validation_notes' => $row->validation_notes,
                'missing_information' => $row->missing_information,
                'duplicate_request_id' => $row->duplicate_request_id,
                'validated_at' => $this->formatDateTime($row->validated_at),
            ])
            ->values()
            ->all();
    }

    private function insertValidationRecord(
        string $requestId,
        string $status,
        ?string $validatorId,
        ?string $notes,
        ?string $missingInformation,
        ?string $duplicateRequestId,
        Carbon $now
    ): void {
        if (! Schema::hasTable('request_validations')) {
            return;
        }

        DB::table('request_validations')->insert([
            'request_id' => $requestId,
            'validation_status' => $status,
            'validator_user_id' => $validatorId,
            'validation_notes' => $notes,
            'missing_information' => $missingInformation,
            'duplicate_request_id' => $duplicateRequestId,
            'validated_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function sourceOptions(): array
    {
        return collect(self::SOURCES)
            ->map(fn (string $label, string $key): array => ['key' => $key, 'label' => $label])
            ->values()
            ->all();
    }

    private function categoryOptions(): array
    {
        return collect(self::CATEGORIES)
            ->map(fn (string $label, string $key): array => ['key' => $key, 'label' => $label])
            ->values()
            ->all();
    }

    private function statusOptions(): array
    {
        return collect(self::VALIDATION_STATUSES)
            ->map(fn (string $label, string $key): array => [
                'key' => $key,
                'label' => $label,
                'status_id' => $this->resourceStatusId($key),
            ])
            ->values()
            ->all();
    }

    private function urgencyOptions(): array
    {
        return DB::table('urgency_levels')
            ->orderBy('urgency_id')
            ->get(['urgency_id', 'urgency_key', 'urgency_label'])
            ->map(fn (object $row): array => [
                'urgency_id' => $row->urgency_id,
                'key' => $row->urgency_key,
                'label' => $row->urgency_label,
            ])
            ->values()
            ->all();
    }

    private function evacuationCenters(): array
    {
        return DB::table('evacuation_centers')
            ->leftJoin('disaster_events as de', 'de.event_id', '=', 'evacuation_centers.current_event_id')
            ->whereNull('evacuation_centers.deleted_at')
            ->orderBy('evacuation_centers.name')
            ->get([
                'evacuation_centers.evacuation_center_id',
                'evacuation_centers.name',
                'evacuation_centers.osm_address',
                'evacuation_centers.current_event_id',
                'de.name as current_event_name',
                'de.ended_at as current_event_ended_at',
                'de.deleted_at as current_event_deleted_at',
            ])
            ->map(function (object $row): array {
                $eventStatus = $this->evacuationEventStatus(
                    $row->current_event_id,
                    $row->current_event_ended_at,
                    $row->current_event_deleted_at
                );

                return [
                    'evacuation_center_id' => $row->evacuation_center_id,
                    'name' => $row->name ?: $row->evacuation_center_id,
                    'address' => $row->osm_address,
                    'current_event_id' => $eventStatus === 'active' ? $row->current_event_id : null,
                    'current_event_name' => $eventStatus === 'active' ? $row->current_event_name : null,
                    'current_event_status' => $eventStatus,
                ];
            })
            ->values()
            ->all();
    }

    private function evacuationEventStatus(?string $eventId, ?string $endedAt, ?string $deletedAt): string
    {
        if (! $eventId) {
            return 'none';
        }

        return $endedAt || $deletedAt ? 'closed' : 'active';
    }

    private function purokOptions(): array
    {
        $puroks = DB::table('addresses')
            ->whereNotNull('purok_sitio')
            ->where('purok_sitio', '<>', '')
            ->select('purok_sitio')
            ->distinct()
            ->orderBy('purok_sitio')
            ->pluck('purok_sitio')
            ->values()
            ->all();

        return empty($puroks) ? ['Purok 1', 'Purok 2', 'Purok 3', 'Purok 4', 'Purok 5'] : $puroks;
    }

    private function getActiveEvent(): ?object
    {
        return DB::table('disaster_events as de')
            ->leftJoin('disaster_types as dt', 'dt.type_id', '=', 'de.type_id')
            ->leftJoin('severity_levels as sl', 'sl.severity_id', '=', 'de.severity_level_id')
            ->whereNull('de.deleted_at')
            ->whereNull('de.ended_at')
            ->orderByDesc('de.started_at')
            ->select([
                'de.event_id',
                'de.name',
                'de.started_at',
                'dt.type_name',
                'sl.severity_key',
                'sl.severity_label',
            ])
            ->first();
    }

    private function formatActiveEvent(?object $event): ?array
    {
        if (! $event) {
            return null;
        }

        return [
            'event_id' => $event->event_id,
            'name' => $event->name,
            'type' => $event->type_name ?? 'Disaster event',
            'severity' => $event->severity_label ?? 'Unspecified',
            'severity_key' => $event->severity_key ?? 'medium',
            'started_at' => $this->formatDateTime($event->started_at),
        ];
    }

    private function validationStatus(?string $status): array
    {
        $key = $this->statusKey($status ?: 'needs_validation');

        return [
            'key' => $key,
            'label' => self::VALIDATION_STATUSES[$key] ?? $this->label($key),
            'tone' => match ($key) {
                'verified', 'fulfilled' => 'green',
                'forwarded' => 'blue',
                'returned', 'cancelled' => 'red',
                default => 'amber',
            },
        ];
    }

    private function handoffStatus(object $row): array
    {
        if ($row->tracking_reference) {
            return [
                'label' => $row->tracking_reference,
                'tone' => 'green',
                'meta' => $row->released_for_tracking_at ? 'accepted ' . $this->formatTime($row->released_for_tracking_at) : 'forwarded',
            ];
        }

        $status = $this->statusKey($row->validation_status ?? 'needs_validation');

        if ($status === 'verified') {
            return ['label' => 'Ready', 'tone' => 'green', 'meta' => 'ready for TrackingAid'];
        }

        return ['label' => 'Not forwarded', 'tone' => 'gray', 'meta' => null];
    }

    private function statusKey(?string $status): string
    {
        $key = strtolower(trim((string) $status));
        $key = str_replace([' ', '-'], '_', $key);

        return match ($key) {
            'validated', 'approved', 'verify' => 'verified',
            'return_for_info', 'rejected', 'duplicate' => 'returned',
            'pending', '' => 'needs_validation',
            default => $key,
        };
    }

    private function sourceKey(?string $source): string
    {
        $key = strtolower(trim((string) $source));
        $key = str_replace([' ', '-'], '_', $key);

        return array_key_exists($key, self::SOURCES) ? $key : ($key ?: 'shared_db');
    }

    private function categoryKey(?string $category): string
    {
        $key = strtolower(trim((string) $category));
        $key = str_replace([' ', '-'], '_', $key);

        return array_key_exists($key, self::CATEGORIES) ? $key : ($key ?: 'resource');
    }

    private function sourceLabel(?string $source): string
    {
        $key = $this->sourceKey($source);

        return self::SOURCES[$key] ?? $this->label($source);
    }

    private function sourceSystem(?string $source): array
    {
        $key = $this->sourceKey($source);
        $isEvaTrack = in_array($key, ['shared_db', 'evatrack', 'evacuation_site'], true);

        return [
            'key' => $isEvaTrack ? 'evatrack' : 'resqperation',
            'label' => $isEvaTrack ? 'EvaTrack' : 'ResQperation',
        ];
    }

    private function categoryLabel(?string $category): string
    {
        $key = $this->categoryKey($category);

        return self::CATEGORIES[$key] ?? $this->label($category);
    }

    private function label(?string $value): string
    {
        return ucwords(str_replace(['_', '-'], ' ', $value ?? 'Unknown'));
    }

    private function resourceStatusId(string $validationStatus): ?int
    {
        $statusKey = match ($this->statusKey($validationStatus)) {
            'verified' => 'approved',
            'forwarded' => 'acknowledged',
            'returned', 'cancelled' => 'rejected',
            'fulfilled' => 'delivered',
            default => 'pending',
        };

        return DB::table('resource_request_status')
            ->where('status_key', $statusKey)
            ->value('status_id');
    }

    private function urgencyId(string $urgencyKey): ?int
    {
        return DB::table('urgency_levels')
            ->where('urgency_key', $urgencyKey)
            ->value('urgency_id');
    }

    private function nextRequestId(): string
    {
        do {
            $requestId = 'RR-' . now()->format('Y') . '-' . strtoupper(Str::random(6));
        } while (DB::table('resource_requests')->where('request_id', $requestId)->exists());

        return $requestId;
    }

    private function nextTrackingReference(): string
    {
        $today = now()->format('Ymd');
        $count = DB::table('resource_requests')
            ->whereDate('released_for_tracking_at', Carbon::today())
            ->count() + 1;

        return 'TA-' . $today . '-' . str_pad((string) $count, 3, '0', STR_PAD_LEFT);
    }

    private function formatDateTime(?string $value): ?string
    {
        return $value ? Carbon::parse($value)->format('M d, Y g:i A') : null;
    }

    private function formatTime(?string $value): ?string
    {
        return $value ? Carbon::parse($value)->format('g:i A') : null;
    }

    private function writeAuditLog(Request $request, string $action, string $requestId, mixed $oldValues, mixed $newValues): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        DB::table('audit_logs')->insert([
            'user_id' => $request->user()?->user_id,
            'role_key' => $request->user()?->role?->role_key,
            'module' => 'resources_requests',
            'action' => $action,
            'reference_table' => 'resource_requests',
            'reference_id' => $requestId,
            'old_values' => $oldValues ? json_encode($oldValues, JSON_UNESCAPED_SLASHES) : null,
            'new_values' => $newValues ? json_encode($newValues, JSON_UNESCAPED_SLASHES) : null,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
            'created_at' => now(),
        ]);
    }
}
