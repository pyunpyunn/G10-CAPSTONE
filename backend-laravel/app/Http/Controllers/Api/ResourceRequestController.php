<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListRequest;
use App\Services\Web\ResourceRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResourceRequestController extends Controller
{
    private ResourceRequestService $service;

    public function __construct(ResourceRequestService $service)
    {
        $this->service = $service;
    }

    public function types(ListRequest $request): JsonResponse
    {
        $query = app(\App\Queries\ResourceRequestQuery::class);
        $connection = (string) config('services.trackingaid.connection', 'trackingaid');
        $inventory = ['data' => [], 'total' => 0];
        $inventoryError = null;
        try {
            if (! \App\Support\RequestSchema::connection($connection)->hasTable('inventory')) {
                throw new \RuntimeException('Inventory unavailable');
            }
            $inventory = \Illuminate\Support\Facades\DB::connection($connection)->table('inventory')
                ->whereNull('deleted_at')->orderBy('name')->paginate($request->integer('per_page', 20));
            $inventory->through(fn ($item) => [
                'label' => $item->name, 'type' => $item->type, 'sku' => $item->sku,
                'quantity' => max(0, (int) $item->quantity),
                'status' => $item->expiration && strtotime($item->expiration) < strtotime('today')
                    ? 'Expired' : ((int) $item->quantity > 0 ? 'Available' : 'Out of stock'),
                'detail' => $item->storage_location,
            ]);
        } catch (\Throwable $error) {
            report($error);
            $inventoryError = 'Inventory could not be loaded from TrackingAid. Please try again.';
        }
        return response()->json(['data' => [
            'types' => $query->options()['categories'], 'inventory' => $inventory,
            'inventory_error' => $inventoryError,
        ]]);
    }

    public function index(ListRequest $request): JsonResponse
    {
        return $this->service->index($request);
    }

    public function show(string $requestId): JsonResponse
    {
        return $this->service->show($requestId);
    }

    public function update(Request $request, string $requestId): JsonResponse
    {
        return $this->service->update($request, $requestId);
    }

    public function store(Request $request): JsonResponse
    {
        return $this->service->store($request);
    }

    public function externalStore(Request $request): JsonResponse
    {
        return $this->service->storeExternal($request);
    }

    public function validateResource(Request $request, string $requestId): JsonResponse
    {
        return $this->service->validateResource($request, $requestId);
    }

    public function forward(Request $request, string $requestId): JsonResponse
    {
        return $this->service->forward($request, $requestId);
    }

    public function returnRequest(Request $request, string $requestId): JsonResponse
    {
        return $this->service->returnRequest($request, $requestId);
    }

    public function complete(Request $request, string $requestId): JsonResponse
    {
        return $this->service->complete($request, $requestId);
    }
}



