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
                        default => ucfirst(strtolower($categoryCode)),
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
            $source = 'resq_local_fallback';
            // Fallback to local resource requests
            $localRequests = \Illuminate\Support\Facades\DB::table('resource_requests')
                ->leftJoin('evacuation_centers', 'evacuation_centers.evacuation_center_id', '=', 'resource_requests.evacuation_center_id')
                ->select([
                    'resource_requests.request_id',
                    'resource_requests.request_category',
                    'resource_requests.resource_type',
                    'resource_requests.item_name',
                    'resource_requests.quantity',
                    'resource_requests.unit',
                    'resource_requests.validation_status',
                    'resource_requests.tracking_reference',
                    'resource_requests.description',
                    'resource_requests.created_at',
                    'evacuation_centers.name as evacuation_center_name',
                ])
                ->orderByDesc('resource_requests.created_at')
                ->get();

            $latestReqId = $localRequests->first()?->request_id;

            foreach ($localRequests as $lr) {
                $qty = max(0, (int) $lr->quantity);
                $isOut = strtolower($lr->validation_status ?? '') === 'returned' || $qty <= 0;
                $rawItems->push([
                    'id' => $lr->request_id,
                    'name' => $lr->item_name ?: (ucfirst($lr->resource_type ?? 'Resource').' Supplies'),
                    'label' => $lr->item_name ?: (ucfirst($lr->resource_type ?? 'Resource').' Supplies'),
                    'category' => strtoupper(trim($lr->request_category ?: 'RESOURCE')),
                    'category_key' => strtolower(trim($lr->request_category ?: 'resource')),
                    'category_label' => ucfirst(strtolower($lr->request_category ?: 'Resource')),
                    'sku' => $lr->tracking_reference ?: $lr->request_id,
                    'unit' => $lr->unit ?: 'units',
                    'type' => ucfirst(strtolower($lr->resource_type ?: 'General')),
                    'quantity' => $qty,
                    'is_out_of_stock' => $isOut,
                    'stock_status' => $isOut ? 'out_of_stock' : 'in_stock',
                    'status' => $isOut ? 'Out of stock' : 'Available',
                    'status_label' => $isOut ? 'Out of stock' : 'Available',
                    'storage_location' => $lr->evacuation_center_name ? 'Evacuation: '.$lr->evacuation_center_name : 'Barangay Resource Depot',
                    'detail' => $lr->evacuation_center_name ? 'Evacuation: '.$lr->evacuation_center_name : 'Barangay Resource Depot',
                    'expiration_date' => null,
                    'is_new' => $lr->request_id === $latestReqId,
                    'created_at' => (string) ($lr->created_at ?? now()),
                ]);
            }
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
        $perPage = min(100, max(1, (int) $request->query('per_page', 24)));
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

    public function storeType(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'unit_type' => ['nullable', 'string', 'max:100'],
            'storage_location' => ['nullable', 'string', 'max:255'],
            'quantity' => ['nullable', 'integer', 'min:0'],
            'type' => ['nullable', 'string', 'in:consumable,returnable'],
            'expiration_date' => ['nullable', 'date'],
        ]);

        $conn = (string) config('services.trackingaid.connection', 'trackingaid');
        $category = strtoupper(trim($validated['category']));
        $nameSlug = \Illuminate\Support\Str::slug($validated['name']);
        $sku = strtoupper($category . '-' . substr($nameSlug, 0, 10) . '-' . strtoupper(\Illuminate\Support\Str::random(4)));
        $now = now();
        $quantity = max(0, (int) ($validated['quantity'] ?? 0));
        $type = strtolower($validated['type'] ?? 'consumable');

        try {
            $itemId = \Illuminate\Support\Facades\DB::connection($conn)->table('items')->insertGetId([
                'name' => trim($validated['name']),
                'category' => $category,
                'unit_type' => $validated['unit_type'] ?: 'Unit',
                'size_weight' => 'STD',
                'target_beneficiary' => 'ALL',
                'variant' => 'NONE',
                'type' => $type,
                'storage_location' => $validated['storage_location'] ?: 'Warehouse A',
                'expiration_date' => $validated['expiration_date'] ?? null,
                'sku' => $sku,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if ($quantity > 0) {
                \Illuminate\Support\Facades\DB::connection($conn)->table('stock_batches')->insert([
                    'item_id' => $itemId,
                    'quantity' => $quantity,
                    'supplier' => 'ResQperation Intake',
                    'date_received' => $now->toDateString(),
                    'expiration_date' => $validated['expiration_date'] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            \Illuminate\Support\Facades\DB::connection($conn)->table('inventory')->updateOrInsert(
                ['sku' => $sku],
                [
                    'name' => trim($validated['name']),
                    'category' => $category,
                    'type' => ucfirst($type),
                    'quantity' => $quantity,
                    'expiration' => $validated['expiration_date'] ?? null,
                    'storage_location' => $validated['storage_location'] ?: 'Warehouse A',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );

            return response()->json([
                'message' => 'New request type created and added to TrackingAid catalog.',
                'item' => [
                    'id' => $itemId,
                    'name' => $validated['name'],
                    'category' => $category,
                    'sku' => $sku,
                    'quantity' => $quantity,
                    'is_new' => true,
                ],
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Failed to declare new request type in shared database.',
                'error' => $e->getMessage(),
            ], 500);
        }
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



