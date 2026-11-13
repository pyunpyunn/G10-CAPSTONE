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
        $sourceFlags = [];

        // 1. Fetch from TrackingAid
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
                    $categoryCode = strtoupper(trim($item->category ?? 'OTHER'));
                    $categoryKey = strtolower($categoryCode);
                    $sku = strtoupper($item->sku ?? ('SKU-' . $item->id));

                    $rawItems->put($sku, [
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
                $sourceFlags[] = 'trackingaid_db';
            }
        } catch (\Throwable $e) {
            // TrackingAid unreachable, fallback gracefully
        }

        // 2. Fetch from Shared DB / Local DB
        $sharedConn = 'shared_resq';
        $defaultConn = config('database.default');
        foreach ([$sharedConn, $defaultConn] as $sConn) {
            if (!$sConn || $sConn === $trackingConn) {
                continue;
            }
            try {
                if (config("database.connections.{$sConn}.database")) {
                    config(["database.connections.{$sConn}.options." . \PDO::ATTR_TIMEOUT => 2]);
                    if (\Illuminate\Support\Facades\Schema::connection($sConn)->hasTable('items')) {
                        $sharedDbItems = \Illuminate\Support\Facades\DB::connection($sConn)->table('items')->whereNull('deleted_at')->get();
                        $sharedStockSums = [];
                        if (\Illuminate\Support\Facades\Schema::connection($sConn)->hasTable('stock_batches')) {
                            $sharedStockSums = \Illuminate\Support\Facades\DB::connection($sConn)->table('stock_batches')
                                ->select('item_id', \Illuminate\Support\Facades\DB::raw('SUM(quantity) as total_qty'))
                                ->groupBy('item_id')
                                ->pluck('total_qty', 'item_id')
                                ->all();
                        }

                        foreach ($sharedDbItems as $item) {
                            $sku = strtoupper($item->sku ?? ('SKU-' . $item->id));
                            $qty = max(0, (int) ($sharedStockSums[$item->id] ?? 0));
                            $categoryCode = strtoupper(trim($item->category ?? 'OTHER'));
                            $categoryKey = strtolower($categoryCode);

                            if ($rawItems->has($sku)) {
                                $existing = $rawItems->get($sku);
                                $existing['quantity'] = max($existing['quantity'], $qty);
                                $existing['is_out_of_stock'] = $existing['quantity'] <= 0;
                                $existing['stock_status'] = $existing['quantity'] > 0 ? 'in_stock' : 'out_of_stock';
                                $existing['status'] = $existing['quantity'] > 0 ? 'Available' : 'Out of stock';
                                $existing['status_label'] = $existing['status'];
                                $existing['source'] = 'unified';
                                $rawItems->put($sku, $existing);
                            } else {
                                $rawItems->put($sku, [
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
                                    'is_new' => false,
                                    'source' => 'shared_db',
                                    'created_at' => (string) ($item->created_at ?? now()),
                                ]);
                            }
                        }
                        $sourceFlags[] = 'shared_db';
                        break;
                    }
                }
            } catch (\Throwable $e) {
                // Shared DB connection skipped
            }
        }

        // 3. Fallback: extract distinct known resource types from resource_requests if catalog is empty
        if ($rawItems->isEmpty()) {
            try {
                if (\Illuminate\Support\Facades\Schema::hasTable('resource_requests')) {
                    $distinctTypes = \App\Models\ResourceRequest::query()
                        ->select('resource_type', 'item_name', 'unit')
                        ->whereNotNull('resource_type')
                        ->distinct()
                        ->limit(50)
                        ->get();

                    $dummyId = 1000;
                    foreach ($distinctTypes as $req) {
                        $name = $req->item_name ?: $req->resource_type;
                        $cat = strtoupper(trim($req->resource_type ?: 'OTHER'));
                        $sku = 'RR-' . strtoupper(\Illuminate\Support\Str::slug($name));
                        if (!$rawItems->has($sku)) {
                            $rawItems->put($sku, [
                                'id' => ++$dummyId,
                                'name' => $name,
                                'label' => $name,
                                'category' => $cat,
                                'category_key' => strtolower($cat),
                                'category_label' => ucfirst(strtolower($cat)),
                                'sku' => $sku,
                                'unit' => $req->unit ?: 'units',
                                'type' => 'Consumable',
                                'quantity' => 100,
                                'is_out_of_stock' => false,
                                'stock_status' => 'in_stock',
                                'status' => 'Available',
                                'status_label' => 'Available',
                                'storage_location' => 'Central Depot',
                                'detail' => 'Central Depot',
                                'expiration_date' => null,
                                'is_new' => false,
                                'source' => 'resource_requests_catalog',
                                'created_at' => (string) now(),
                            ]);
                        }
                    }
                    if ($distinctTypes->isNotEmpty()) {
                        $sourceFlags[] = 'shared_db';
                    }
                }
            } catch (\Throwable $e) {
                // Schema check failed
            }
        }

        $source = empty($sourceFlags) ? 'database_empty' : (count($sourceFlags) > 1 ? 'trackingaid_db' : $sourceFlags[0]);
        $itemsCollection = $rawItems->values();

        // Apply filters
        $filtered = clone $itemsCollection;

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
        $inStockCount = $itemsCollection->where('is_out_of_stock', false)->count();
        $outOfStockCount = $itemsCollection->where('is_out_of_stock', true)->count();
        $totalAvailableUnits = (int) $itemsCollection->where('is_out_of_stock', false)->sum('quantity');

        // Dynamic categories with counts
        $categoriesCounts = $itemsCollection->groupBy('category_key');
        $categoriesList = [
            ['key' => 'all', 'label' => 'All Categories', 'count' => $itemsCollection->count()],
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
                'total_types' => $itemsCollection->count(),
                'in_stock_count' => $inStockCount,
                'out_of_stock_count' => $outOfStockCount,
                'total_available_units' => $totalAvailableUnits,
            ],
            'categories' => $categoriesList,
            'cards' => $paginator,
            'types' => $categoriesList,
            'inventory' => $paginator,
            'source' => $source,
            'sources_available' => $sourceFlags,
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

        $trackingConn = (string) config('services.trackingaid.connection', 'trackingaid');
        $sharedConn = 'shared_resq';
        $defaultConn = config('database.default');

        $category = strtoupper(trim($validated['category']));
        $nameSlug = \Illuminate\Support\Str::slug($validated['name']);
        $sku = strtoupper($category . '-' . substr($nameSlug, 0, 10) . '-' . strtoupper(\Illuminate\Support\Str::random(4)));
        $now = now();
        $quantity = max(0, (int) ($validated['quantity'] ?? 0));
        $type = strtolower($validated['type'] ?? 'consumable');

        $trackingSaved = false;
        $trackingItemId = null;
        $sharedSaved = false;

        // 1. Persist to TrackingAid
        try {
            config(["database.connections.{$trackingConn}.options." . \PDO::ATTR_TIMEOUT => 2]);
            if (\Illuminate\Support\Facades\Schema::connection($trackingConn)->hasTable('items')) {
                $trackingItemId = \Illuminate\Support\Facades\DB::connection($trackingConn)->table('items')->insertGetId([
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
            }
        } catch (\Throwable $e) {
            report($e);
        }

        // 2. Persist to Shared DB / Local DB
        foreach ([$sharedConn, $defaultConn] as $targetConn) {
            if (!$targetConn || $targetConn === $trackingConn) {
                continue;
            }
            try {
                if (config("database.connections.{$targetConn}.database")) {
                    config(["database.connections.{$targetConn}.options." . \PDO::ATTR_TIMEOUT => 2]);
                    if (\Illuminate\Support\Facades\Schema::connection($targetConn)->hasTable('items')) {
                        $sharedId = \Illuminate\Support\Facades\DB::connection($targetConn)->table('items')->insertGetId([
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

                        if ($quantity > 0 && \Illuminate\Support\Facades\Schema::connection($targetConn)->hasTable('stock_batches')) {
                            \Illuminate\Support\Facades\DB::connection($targetConn)->table('stock_batches')->insert([
                                'item_id' => $sharedId,
                                'quantity' => $quantity,
                                'supplier' => 'ResQperation Intake',
                                'date_received' => $now->toDateString(),
                                'expiration_date' => $validated['expiration_date'] ?? null,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ]);
                        }

                        if (\Illuminate\Support\Facades\Schema::connection($targetConn)->hasTable('inventory')) {
                            \Illuminate\Support\Facades\DB::connection($targetConn)->table('inventory')->updateOrInsert(
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

                        $sharedSaved = true;
                        break;
                    }
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        if ($trackingSaved || $sharedSaved) {
            return response()->json([
                'message' => 'New request type created and synchronized across catalog.',
                'item' => [
                    'id' => $trackingItemId ?: 1,
                    'name' => $validated['name'],
                    'category' => $category,
                    'sku' => $sku,
                    'quantity' => $quantity,
                    'is_new' => true,
                ],
                'synchronized' => [
                    'trackingaid' => $trackingSaved,
                    'shared_db' => $sharedSaved,
                ],
            ], 201);
        }

        return response()->json([
            'message' => 'Failed to declare new request type in shared database and TrackingAid.',
        ], 500);
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



