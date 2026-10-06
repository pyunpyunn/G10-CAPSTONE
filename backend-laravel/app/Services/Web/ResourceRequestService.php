<?php

namespace App\Services\Web;

use App\Models\ResourceRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Support\RequestSchema as Schema;
use RuntimeException;

class ResourceRequestService
{
    private \App\Services\Shared\TrackingAidForwardingService $trackingAid;
    private \App\Presenters\ResourceRequestPresenter $presenter;
    private \App\Queries\ResourceRequestQuery $query;
    private \App\Services\Web\ResourceRequestWriteWorkflow $workflow;
    private \App\Services\Shared\ExternalResourceRequestWorkflow $externalWorkflow;
    private \App\Services\Web\ResourceRequestPayloadValidator $validator;
    private \App\Services\Shared\ExternalRequestIntakeAuthorization $intakeAuthorization;

    public function __construct(\App\Services\Shared\TrackingAidForwardingService $trackingAid)
    {
        $this->trackingAid = $trackingAid;
        $this->presenter = app(\App\Presenters\ResourceRequestPresenter::class);
        $this->query = app(\App\Queries\ResourceRequestQuery::class);
        $this->workflow = app(\App\Services\Web\ResourceRequestWriteWorkflow::class);
        $this->externalWorkflow = app(\App\Services\Shared\ExternalResourceRequestWorkflow::class);
        $this->validator = app(\App\Services\Web\ResourceRequestPayloadValidator::class);
        $this->intakeAuthorization = app(\App\Services\Shared\ExternalRequestIntakeAuthorization::class);
    }

    public function index(Request $request): JsonResponse
    {
        [$paginator, $coreOnly, $period] = $this->query->list($request);
        $rows = $paginator->items();
        $references = [];
        foreach ($rows as $row) {
            $references[$row->request_id] = (string) ($row->tracking_reference ?? '');
        }
        $handoffStatuses = $this->trackingAid->requestHandoffStatuses($references);
        $items = collect($rows)->map(fn (object $row): array => $this->presenter->format($row, false, [], $handoffStatuses))->values()->all();
        return response()->json([
            'data' => [
                'area_label' => app(\App\Queries\AreaCoverageQuery::class)->label(),
                'active_event' => $coreOnly ? null : $this->presenter->activeEvent($this->query->activeEvent()),
                'summary' => $coreOnly
                    ? $this->query->coreSummary($items, $paginator->total())
                    : $this->query->summary($period),
                'requests' => [
                    'data' => $items,
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'last_page' => $paginator->lastPage(),
                    'total' => $paginator->total(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                ],
                'tracking_mirror' => $this->trackingAid->acknowledgedResourceRequests(),
                'options' => $coreOnly ? [] : $this->query->options(),

                'scope_note' => 'RESQPERATION validates requests only. TrackingAid owns release, delivery, and fulfillment after handoff.',
            ],
        ]);
    }

    public function show(string $requestId): JsonResponse
    {
        $resourceRequest = $this->query->find($requestId);

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
        $validated = $this->validator->request($request);
        $activeEvent = $this->query->activeEvent();

        $created = $this->workflow->create($request, $validated, $activeEvent);

        return response()->json([
            'message' => 'Request saved for validation.',
            'data' => [
                'request' => $created,
            ],
        ], 201);
    }

    public function update(Request $request, string $requestId): JsonResponse
    {
        $resourceRequest = ResourceRequest::query()->where('request_id', $requestId)->first();

        if (! $resourceRequest) {
            return response()->json([
                'message' => 'Resource request was not found.',
            ], 404);
        }

        $currentStatus = $this->presenter->statusKey($resourceRequest->validation_status ?? 'needs_validation');

        if (! in_array($currentStatus, ['needs_validation', 'returned'], true)) {
            return response()->json([
                'message' => 'Only pending or returned requests can be edited.',
            ], 422);
        }

        $validated = $this->validator->request($request);

        $updated = $this->workflow->update($request, $resourceRequest, $requestId, $validated);

        return response()->json([
            'message' => 'Resource request updated.',
            'data' => [
                'request' => $updated,
            ],
        ]);
    }

    public function storeExternal(Request $request): JsonResponse
    {
        $authError = $this->intakeAuthorization->error($request);

        if ($authError) {
            return $authError;
        }

        if (! Schema::hasTable('resource_requests')) {
            return response()->json([
                'message' => 'RESQPERATION cannot receive requests because resource_requests is missing.',
            ], 503);
        }

        $validated = $this->externalWorkflow->validatePayload($request);
        $normalized = $this->externalWorkflow->normalize($validated);
        $saved = $this->externalWorkflow->store($request, $validated, $normalized, $this->workflow);
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
        $resourceRequest = $this->query->find($requestId);

        if (! $resourceRequest) {
            return response()->json([
                'message' => 'Resource request was not found.',
            ], 404);
        }

        $validated = $this->validator->decision($request);

        $updated = $this->workflow->validate($request, $requestId, $resourceRequest, $validated);

        return response()->json([
            'message' => 'Validation record saved.',
            'data' => [
                'request' => $updated,
            ],
        ]);
    }

    public function forward(Request $request, string $requestId): JsonResponse
    {
        $resourceRequest = $this->query->find($requestId);

        if (! $resourceRequest) {
            return response()->json([
                'message' => 'Resource request was not found.',
            ], 404);
        }

        $currentStatus = $this->presenter->statusKey($resourceRequest->validation_status ?? 'needs_validation');

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
            $updated = $this->workflow->forward($request, $requestId, $resourceRequest, $validated);
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
        $resourceRequest = $this->query->find($requestId);

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

        $updated = $this->workflow->returnRequest($request, $requestId, $resourceRequest, $validated);

        return response()->json([
            'message' => 'Request returned for missing information or duplicate check.',
            'data' => [
                'request' => $updated,
            ],
        ]);
    }

    public function complete(Request $request, string $requestId): JsonResponse
    {
        $resourceRequest = $this->query->find($requestId);

        if (! $resourceRequest) {
            return response()->json([
                'message' => 'Resource request was not found.',
            ], 404);
        }

        $validated = $request->validate([
            'validation_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $updated = $this->workflow->complete($request, $requestId, $resourceRequest, $validated);

        return response()->json([
            'message' => 'Request marked as completed.',
            'data' => [
                'request' => $updated,
            ],
        ]);
    }

    private function formatRequest(?object $row, bool $includeDetails = false): ?array
    {
        $history = $includeDetails && $row ? $this->query->validationHistory($row->request_id) : [];
        return $this->presenter->format($row, $includeDetails, $history);
    }
}







