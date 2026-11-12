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

    public function types(Request $request): JsonResponse
    {
        $conn = (string) config('services.trackingaid.connection', 'trackingaid');
        $rawItems = collect();
        $source = 'trackingaid_db';

        try {
            config(["database.connections.{$conn}.options." . \PDO::ATTR_TIMEOUT => 1]);
            $dbItems = \Illuminate\Support\Facades\DB::connection($conn)->table('items')->whereNull('deleted_at')->get();

            $stockSums = \Illuminate\Support\Facades\DB::connection($conn)->table('stock_batches')
                ->select('item_id', \Illuminate\Support\Facades\DB::raw('SUM(quantity) as total_qty'))
                ->groupBy('item_id')
                ->pluck('total_qty', 'item_id')
                ->all();

            $latestItemId = $dbItems->sortByDesc(fn ($i) => [$i->created_at ?? '', $i->id])->first()?->id;

            foreach ($dbItems as $item) {
                $qty = max(0, (int) ($stockSums[$item->id] ?? 0));
                $isNew = ($item->id === $latestItemId);
                $categoryCode = strtoupper(trim($item->category ?? 'OTHER'));
                $categoryKey = strtolower($categoryCode);

                $rawItems->push([
                    'id' => (int) $item->id,
                    'name' => $item->name,
                    'label' => $item->name,
                    'category' => $categoryCode,
                    'category_key' => $categoryKey,
                    'category_label' => match ($categoryCode) {
                        'FOOD' => 'Food & Water',
                        'MEDICAL' => 'Medical Supplies',
                        'RESCUE' => 'Rescue Equipment',
                        'RELIEF' => 'Relief Goods',
                        'SHELTER' => 'Emergency Shelter',
                        default => ucfirst(strtolower(str_replace('_', ' ', $categoryCode))),
                    },
                    'sku' => $item->sku,
                    'unit' => $item->unit_type ?: 'units',
                    'type' => ucfirst(strtolower($item->type ?: 'consumable')),
                    'quantity' => $qty,
                    'is_out_of_stock' => $qty <= 0,
                    'stock_status' => $qty > 0 ? 'in_stock' : 'out_of_stock',
                    'status' => $qty > 0 ? 'Available' : 'Out of stock',
                    'status_label' => $qty > 0 ? 'Available' : 'Out of stock',
                    'storage_location' => $item->storage_location ?: 'Warehouse Depot',
                    'detail' => $item->storage_location ?: 'Warehouse Depot',
                    'expiration_date' => $item->expiration_date ?: null,
                    'is_new' => $isNew,
                    'created_at' => (string) ($item->created_at ?? now()),
                ]);
            }
        } catch (\Throwable $e) {
            $source = 'database_empty';
            $rawItems = collect();
        }

        // Apply filters
        $filtered = clone $rawItems;

        $categoryFilter = strtolower(trim((string) $request->query('category', 'all')));
        if ($categoryFilter !== '' && $categoryFilter !== 'all') {
            $filtered = $filtered->filter(fn ($item) => strtolower($item['category_key']) === $categoryFilter || strtolower($item['category']) === strtoupper($categoryFilter));
        }

        $statusFilter = strtolower(trim((string) $request->query('status', 'all')));
        if ($statusFilter === 'in_stock') {
            $filtered = $filtered->filter(fn ($item) => ! $item['is_out_of_stock']);
        } elseif ($statusFilter === 'out_of_stock') {
            $filtered = $filtered->filter(fn ($item) => $item['is_out_of_stock']);
        }

        $search = strtolower(trim((string) $request->query('search', '')));
        if ($search !== '') {
            $filtered = $filtered->filter(fn ($item) => str_contains(strtolower($item['name']), $search) || str_contains(strtolower($item['sku'] ?? ''), $search) || str_contains(strtolower($item['storage_location'] ?? ''), $search));
        }

        // Sort: is_new DESC (1st card priority), then created_at DESC
        $sortOption = (string) $request->query('sort', 'newest');
        $sorted = (match ($sortOption) {
            'name' => $filtered->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE),
            'quantity_desc' => $filtered->sortByDesc('quantity'),
            default => $filtered->sortBy([
                ['is_new', 'desc'],
                ['created_at', 'desc'],
                ['id', 'desc'],
            ]),
        })->values();

        // Pagination
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(1, (int) $request->query('per_page', 10)));
        $total = $sorted->count();
        $offset = ($page - 1) * $perPage;
        $pageItems = $sorted->slice($offset, $perPage)->values()->all();

        $paginator = [
            'current_page' => $page,
            'data' => $pageItems,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => max(1, (int) ceil($total / $perPage)),
            'from' => $total > 0 ? $offset + 1 : 0,
            'to' => min($offset + $perPage, $total),
        ];

        // Summary metrics
        $inStockCount = $rawItems->where('is_out_of_stock', false)->count();
        $outOfStockCount = $rawItems->where('is_out_of_stock', true)->count();
        $totalAvailableUnits = (int) $rawItems->where('is_out_of_stock', false)->sum('quantity');

        // Dynamic categories with counts
        $categoriesCounts = $rawItems->groupBy('category_key');
        $categoriesList = [
            ['key' => 'all', 'label' => 'All Categories', 'count' => $rawItems->count()],
        ];
        foreach ($categoriesCounts as $cKey => $group) {
            $categoriesList[] = [
                'key' => $cKey,
                'label' => $group->first()['category_label'] ?? ucfirst($cKey),
                'count' => $group->count(),
            ];
        }

        return response()->json(['data' => [
            'summary' => [
                'total_types' => $rawItems->count(),
                'in_stock_count' => $inStockCount,
                'out_of_stock_count' => $outOfStockCount,
                'total_available_units' => $totalAvailableUnits,
            ],
            'categories' => $categoriesList,
            'cards' => $paginator,
            'types' => $categoriesList,
            'inventory' => $paginator,
            'source' => $source,
            'inventory_error' => null,
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

    public function externalHandshake(Request $request): JsonResponse
    {
        $error = app(\App\Services\Shared\ExternalRequestIntakeAuthorization::class)->error($request);
        if ($error) {
            return $error;
        }

        try {
            foreach (['resource_requests', 'resource_request_status', 'request_validations', 'audit_logs'] as $table) {
                if (! \Illuminate\Support\Facades\Schema::hasTable($table)) {
                    return response()->json(['message' => 'External request storage is not ready.'], 503);
                }
            }
        } catch (\Throwable $error) {
            report($error);
            return response()->json(['message' => 'External request storage is unavailable.'], 503);
        }

        return response()->json([
            'message' => 'External request handshake successful.',
            'data' => [
                'authenticated' => true,
                'request_storage_ready' => true,
                'api_version' => 'v1',
                'intake_endpoint' => '/api/v1/external/resource-requests',
                'intake_method' => 'POST',
                'authentication' => 'integration_key_on_every_request',
                'server_time' => now()->toIso8601String(),
            ],
        ])->header('Cache-Control', 'no-store');
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



