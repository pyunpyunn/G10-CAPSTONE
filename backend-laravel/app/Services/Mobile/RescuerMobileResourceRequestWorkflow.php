<?php

namespace App\Services\Mobile;

use App\Models\ResourceRequest;
use App\Models\ResourceRequestStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Support\RequestSchema as Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RescuerMobileResourceRequestWorkflow
{
    public function __construct(private \App\Services\Mobile\RescuerMobileSupport $support) {}

    public function store(Request $request): JsonResponse
    {
        if (! Schema::hasTable('resource_requests')) {
            return $this->missingTableResponse('resource_requests');
        }

        $validated = $request->validate([
            'location' => ['required', 'string', 'min:3', 'max:255'],
            'cluster' => [Rule::requiredIf(fn () => in_array($request->input('request_category'), ['personnel', 'vehicle'], true)), 'nullable', 'string', 'max:100'],
            'request_category' => ['required', Rule::in(['resource', 'personnel', 'vehicle'])],
            'resource_type' => ['required', 'string', 'min:2', 'max:100'],
            'item_name' => ['nullable', 'string', 'max:150'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'unit' => ['required', 'string', 'min:2', 'max:50'],
            'description' => ['required', 'string', 'min:5', 'max:1000'],
            'urgency_key' => ['required', Rule::in(['low', 'medium', 'high', 'urgent', 'critical'])],
        ], [
            'location.required' => 'Location is required.',
            'cluster.required' => 'Purok or cluster is required for personnel and vehicle requests.',
            'request_category.required' => 'Request type is required.',
            'resource_type.required' => 'Need/category is required.',
            'quantity.required' => 'Quantity is required.',
            'unit.required' => 'Unit is required.',
            'description.required' => 'Reason or field note is required.',
        ]);

        $user = $request->user();
        $responder = $this->support->responderForUser($user);
        $activeEvent = $this->support->activeEvent();
        $now = now();
        $requestId = $this->nextRequestId();

        ResourceRequest::create($this->support->filterColumns('resource_requests', [
            'request_id' => $requestId,
            'request_source' => 'rescuer_mobile',
            'source_reference' => $activeEvent->event_id ?? null,
            'request_category' => $validated['request_category'],
            'evacuation_center_id' => null,
            'requested_by' => $responder?->full_name ?? $user?->full_name ?? $user?->username ?? 'Rescuer mobile',
            'handled_by' => $user?->user_id,
            'resource_type' => $validated['resource_type'],
            'item_name' => $validated['item_name'] ?? null,
            'quantity' => $validated['quantity'],
            'unit' => $validated['unit'] ?? null,
            'description' => $this->description($validated),
            'urgency_id' => $this->urgencyId($validated['urgency_key'] ?? 'medium'),
            'status_id' => $this->statusId('needs_validation'),
            'validation_status' => 'needs_validation',
            'created_at' => $now,
            'updated_at' => $now,
        ]));

        $this->support->writeAuditLog($request, 'mobile_resource_request', 'resource_requests', $requestId, $validated);

        return response()->json([
            'message' => 'Resource request submitted for HQ validation.',
            'data' => ['request_id' => $requestId],
        ], 201);
    }

    public function cancel(Request $request, string $requestId): JsonResponse
    {
        if (! Schema::hasTable('resource_requests')) {
            return $this->missingTableResponse('resource_requests');
        }

        $query = ResourceRequest::query()->where('request_id', $requestId);

        if (Schema::hasColumn('resource_requests', 'handled_by')) {
            $query->where('handled_by', $request->user()?->user_id);
        }

        $resourceRequest = $query->first();

        if (! $resourceRequest) {
            return response()->json(['message' => 'Resource request was not found for your account.'], 404);
        }

        if (($resourceRequest->validation_status ?? '') !== 'needs_validation') {
            return response()->json(['message' => 'Only requests still waiting for validation can be cancelled from mobile.'], 409);
        }

        ResourceRequest::query()
            ->where('request_id', $requestId)
            ->update($this->support->filterColumns('resource_requests', [
                'validation_status' => 'cancelled',
                'status_id' => $this->statusId('cancelled'),
                'updated_at' => now(),
            ]));

        $this->support->writeAuditLog($request, 'mobile_cancel_resource_request', 'resource_requests', $requestId, [
            'validation_status' => 'cancelled',
        ]);

        return response()->json(['message' => 'Resource request cancelled.']);
    }

    private function nextRequestId(): string
    {
        do {
            $requestId = 'RR-'.now()->format('Y').'-'.strtoupper(Str::random(6));
        } while (Schema::hasTable('resource_requests') && ResourceRequest::query()->where('request_id', $requestId)->exists());

        return $requestId;
    }

    private function urgencyId(string $urgencyKey): ?int
    {
        if (! Schema::hasTable('urgency_levels')) {
            return null;
        }

        return DB::table('urgency_levels')->where('urgency_key', $urgencyKey)->value('urgency_id')
            ?: DB::table('urgency_levels')->orderBy('urgency_id')->value('urgency_id');
    }

    private function statusId(string $statusKey): ?int
    {
        if (! Schema::hasTable('resource_request_status')) {
            return null;
        }

        return ResourceRequestStatus::query()->where('status_key', $statusKey)->value('status_id');
    }

    private function description(array $validated): ?string
    {
        $parts = [];
        $location = trim((string) ($validated['location'] ?? ''));
        $cluster = trim((string) ($validated['cluster'] ?? ''));
        $description = trim((string) ($validated['description'] ?? ''));

        if ($location !== '') {
            $parts[] = 'Location: '.$location;
        }
        if ($cluster !== '') {
            $parts[] = 'Cluster: '.$cluster;
        }
        if ($description !== '') {
            $parts[] = $description;
        }

        return $parts ? implode("\n", $parts) : null;
    }

    private function missingTableResponse(string $table): JsonResponse
    {
        return response()->json([
            'message' => 'The required '.$table.' table is not available in the current database.',
        ], 503);
    }
}







