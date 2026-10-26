<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Web\GlobalSearchService;
use App\Services\Web\WebAccessPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GlobalSearchController extends Controller
{
    private const TYPE_MODULES = [
        'events' => 'response_operations',
        'broadcasts' => 'response_operations',
        'households' => 'geospatial_analytics',
        'responders' => 'personnel_access',
        'teams' => 'personnel_access',
        'dispatches' => 'response_operations',
        'resources' => 'resources_requests',
        'sitreps' => 'reports_documentation',
    ];

    public function __construct(private GlobalSearchService $searchService) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
            'types' => ['nullable', 'array', 'max:8'],
            'types.*' => ['required', 'string', Rule::in(GlobalSearchService::TYPES)],
        ]);

        $user = $request->user();
        $modules = $user ? app(WebAccessPolicy::class)->modulesFor($user->loadMissing('role')) : [];
        $types = array_values(array_filter(
            $validated['types'] ?? GlobalSearchService::TYPES,
            fn (string $type): bool => in_array(self::TYPE_MODULES[$type], $modules, true)
        ));

        return response()->json([
            'data' => [
                'results' => $this->searchService->search($validated['q'], $types),
            ],
        ]);
    }
}


