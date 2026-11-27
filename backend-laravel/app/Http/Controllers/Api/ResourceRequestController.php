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
        $rawItems = collect();
        $source = 'database_empty';
        $trackingConn = (string) config('services.trackingaid.connection', 'trackingaid');

        try {
            config(["database.connections.{$trackingConn}.options." . \PDO::ATTR_TIMEOUT => 2]);
            if (\Illuminate\Support\Facades\Schema::connection($trackingConn)->hasTable('items')) {
                $dbItems = \Illuminate\Support\Facades\DB::connection($trackingConn)->table('items')->whereNull('deleted_at')->get();

                $stockSums = [];
                if (\Illuminate\Support\Facades\Schema::connection($trackingConn)->hasTable('stock_batches')) {
                    $stockSums = \Illuminate\Support\Facades\DB::connection($trackingConn)->table('stock_batches')
                        ->select('item_id', \Illuminate\Support\Facades\DB::raw('SUM(quantity) as total_qty'))
                        ->groupBy('item_id')
                        ->pluck('total_qty', 'item_id')
                        ->all();
                }

                $latestItemId = $dbItems->sortByDesc(fn ($i) => [$i->created_at ?? '', $i->id])->first()?->id;

                foreach ($dbItems as $item) {
                    $qty = max(0, (int) ($stockSums[$item->id] ?? 0));
                    $isNew = ($item->id === $latestItemId);
                    $categoryRaw = trim((string) ($item->category ?? ''));
                    $categoryCode = $categoryRaw !== '' ? $categoryRaw : 'Uncategorized';
                    $categoryKey = strtolower($categoryCode);
                    $sku = strtoupper($item->sku ?? ('SKU-' . $item->id));

                    $rawItems->push([
                        'id' => (int) $item->id,
                        'name' => $item->name,
                        'label' => $item->name,
                        'category' => $categoryCode,
                        'category_key' => $categoryKey,
                        'category_label' => $categoryCode,
                        'sku' => $sku,
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
                        'source' => 'trackingaid_db',
                        'created_at' => (string) ($item->created_at ?? now()),
                    ]);
                }

                $source = 'trackingaid_db';
            }
        } catch (\Throwable $e) {
            // TrackingAid unreachable, fallback gracefully
        }

        // 2. Fetch / merge from local system DB (resq_local) so system items are always counted
        $defaultConn = config('database.default');
        try {
            if (\Illuminate\Support\Facades\Schema::connection($defaultConn)->hasTable('items')) {
                $localItems = \Illuminate\Support\Facades\DB::connection($defaultConn)->table('items')->whereNull('deleted_at')->get();
                $existingSkus = $rawItems->pluck('sku')->map(fn ($s) => strtoupper((string) $s))->all();
                $existingNames = $rawItems->pluck('name')->map(fn ($n) => strtolower(trim((string) $n)))->all();

                foreach ($localItems as $item) {
                    $itemSku = strtoupper($item->sku ?? ('SKU-' . $item->id));
                    $itemName = strtolower(trim((string) $item->name));
                    if (in_array($itemSku, $existingSkus, true) || in_array($itemName, $existingNames, true)) {
                        continue;
                    }

                    $categoryRaw = trim((string) ($item->category ?? ''));
                    $categoryCode = $categoryRaw !== '' ? $categoryRaw : 'Uncategorized';
                    $categoryKey = strtolower($categoryCode);

                    $rawItems->push([
                        'id' => (int) $item->id,
                        'name' => $item->name,
                        'label' => $item->name,
                        'category' => $categoryCode,
                        'category_key' => $categoryKey,
                        'category_label' => $categoryCode,
                        'sku' => $itemSku,
                        'unit' => $item->unit_type ?: 'units',
                        'type' => ucfirst(strtolower($item->type ?: 'consumable')),
                        'quantity' => 0,
                        'is_out_of_stock' => true,
                        'stock_status' => 'out_of_stock',
                        'status' => 'Out of stock',
                        'status_label' => 'Out of stock',
                        'storage_location' => $item->storage_location ?: 'Warehouse Depot',
                        'detail' => $item->storage_location ?: 'Warehouse Depot',
                        'expiration_date' => $item->expiration_date ?: null,
                        'is_new' => false,
                        'source' => 'local_db',
                        'created_at' => (string) ($item->created_at ?? now()),
                    ]);

                    if ($source === 'database_empty') {
                        $source = 'local_db';
                    }
                }

                if ($source === 'database_empty' && $rawItems->isNotEmpty()) {
                    $source = 'local_db';
                }
            }
        } catch (\Throwable $e) {
            // Local DB fallback
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

        // Summary metrics: reflect active query filter when filtering, or entire catalog
        $hasActiveFilter = ($categoryFilter !== '' && $categoryFilter !== 'all')
            || ($statusFilter !== '' && $statusFilter !== 'all')
            || ($search !== '');

        $targetItems = $hasActiveFilter ? $filtered : $rawItems;

        $inStockCount = $targetItems->where('is_out_of_stock', false)->count();
        $outOfStockCount = $targetItems->where('is_out_of_stock', true)->count();
        $totalAvailableUnits = (int) $targetItems->where('is_out_of_stock', false)->sum('quantity');

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
                'total_types' => $targetItems->count(),
                'in_stock_count' => $inStockCount,
                'out_of_stock_count' => $outOfStockCount,
                'total_available_units' => $totalAvailableUnits,
                'catalog_total_types' => $rawItems->count(),
                'catalog_in_stock' => $rawItems->where('is_out_of_stock', false)->count(),
                'catalog_out_of_stock' => $rawItems->where('is_out_of_stock', true)->count(),
                'catalog_total_units' => (int) $rawItems->where('is_out_of_stock', false)->sum('quantity'),
            ],
            'categories' => $categoriesList,
            'cards' => $paginator,
            'types' => $categoriesList,
            'inventory' => $paginator,
            'source' => $source,
            'sources_available' => ['trackingaid_db' => $source === 'trackingaid_db'],
            'inventory_error' => null,
        ]]);
    }

    public function storeType(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'resource_type' => ['nullable', 'string', 'max:100'],
            'unit_type' => ['nullable', 'string', 'max:100'],
            'storage_location' => ['nullable', 'string', 'max:255'],
            'quantity' => ['nullable', 'integer', 'min:0'],
            'type' => ['nullable', 'string', 'in:consumable,returnable'],
            'expiration_date' => ['nullable', 'date'],
        ]);

        $trackingConn = (string) config('services.trackingaid.connection', 'trackingaid');
        $sharedConn = 'shared_resq';
        $defaultConn = config('database.default');

        $category = trim((string) ($validated['resource_type'] ?? $validated['category'] ?? ''));
        if ($category === '') {
            return response()->json(['message' => 'The resource type field is required.'], 422);
        }
        $nameSlug = \Illuminate\Support\Str::slug($validated['name']);
        $sku = strtoupper($category . '-' . substr($nameSlug, 0, 10) . '-' . strtoupper(\Illuminate\Support\Str::random(4)));
        $now = now();
        $quantity = max(0, (int) ($validated['quantity'] ?? 0));
        $type = strtolower($validated['type'] ?? 'consumable');

        $trackingSaved = false;
        $trackingItemId = null;
        $sharedSaved = false;

        // 1. Persist to TrackingAid (Primary Authority)
        try {
            config(["database.connections.{$trackingConn}.options." . \PDO::ATTR_TIMEOUT => 2]);
            if (! \Illuminate\Support\Facades\Schema::connection($trackingConn)->hasTable('items')) {
                return response()->json([
                    'message' => 'TrackingAid database is unreachable. Request types cannot be declared while offline.',
                ], 503);
            }

            $columns = \Illuminate\Support\Facades\Schema::connection($trackingConn)->getColumnListing('items');
            $insertRow = [
                'name' => trim($validated['name']),
                'category' => $category,
                'unit_type' => $validated['unit_type'] ?: null,
                'sku' => $sku,
                'type' => $type,
                'storage_location' => $validated['storage_location'] ?: null,
                'expiration_date' => $validated['expiration_date'] ?? null,
                'size_weight' => 'STD',
                'target_beneficiary' => 'ALL',
                'variant' => 'NONE',
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (!empty($columns)) {
                $insertRow = array_intersect_key($insertRow, array_flip($columns));
            }

            $trackingItemId = \Illuminate\Support\Facades\DB::connection($trackingConn)->table('items')->insertGetId($insertRow);

            if ($quantity > 0 && \Illuminate\Support\Facades\Schema::connection($trackingConn)->hasTable('stock_batches')) {
                \Illuminate\Support\Facades\DB::connection($trackingConn)->table('stock_batches')->insert([
                    'item_id' => $trackingItemId,
                    'quantity' => $quantity,
                    'supplier' => 'ResQperation Intake',
                    'date_received' => $now->toDateString(),
                    'expiration_date' => $validated['expiration_date'] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            if (\Illuminate\Support\Facades\Schema::connection($trackingConn)->hasTable('inventory')) {
                \Illuminate\Support\Facades\DB::connection($trackingConn)->table('inventory')->updateOrInsert(
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
            }

            $trackingSaved = true;
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Failed to declare request type: TrackingAid is disconnected.',
                'error' => $e->getMessage(),
            ], 503);
        }

        // 2. Optionally mirror to Shared DB if available
        if (config("database.connections.{$sharedConn}.database")) {
            try {
                config(["database.connections.{$sharedConn}.options." . \PDO::ATTR_TIMEOUT => 2]);
                if (\Illuminate\Support\Facades\Schema::connection($sharedConn)->hasTable('items')) {
                    \Illuminate\Support\Facades\DB::connection($sharedConn)->table('items')->insertGetId([
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
                    $sharedSaved = true;
                }
            } catch (\Throwable $e) {
                // Shared mirror optional
            }
        }

        // 3. Mirror to local DB (resq_local / mysql) so the local system always reflects it
        $localSaved = false;
        try {
            if (\Illuminate\Support\Facades\Schema::connection($defaultConn)->hasTable('items')) {
                $localCols = \Illuminate\Support\Facades\Schema::connection($defaultConn)->getColumnListing('items');
                $localRow = [
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
                ];
                if (!empty($localCols)) {
                    $localRow = array_intersect_key($localRow, array_flip($localCols));
                }
                \Illuminate\Support\Facades\DB::connection($defaultConn)->table('items')->updateOrInsert(
                    ['sku' => $sku],
                    $localRow
                );
                $localSaved = true;
            }
        } catch (\Throwable $e) {
            // Local mirror optional
        }

        return response()->json([
            'message' => 'New request type created and added to TrackingAid catalog.',
            'item' => [
                'id' => $trackingItemId,
                'name' => $validated['name'],
                'category' => $category,
                'sku' => $sku,
                'quantity' => $quantity,
                'is_new' => true,
            ],
            'synchronized' => [
                'trackingaid' => $trackingSaved,
                'shared_db' => $sharedSaved,
                'local_db' => $localSaved,
            ],
        ], 201);
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

    public function cancelRequest(Request $request, string $requestId): JsonResponse
    {
        return $this->service->cancelRequest($request, $requestId);
    }

    public function complete(Request $request, string $requestId): JsonResponse
    {
        return $this->service->complete($request, $requestId);
    }
}



