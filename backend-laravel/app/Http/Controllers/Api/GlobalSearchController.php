<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Web\GlobalSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GlobalSearchController extends Controller
{
    public function __construct(private GlobalSearchService $searchService) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
            'types' => ['nullable', 'array', 'max:7'],
            'types.*' => ['required', 'string', Rule::in(GlobalSearchService::TYPES)],
        ]);

        $types = $validated['types'] ?? GlobalSearchService::TYPES;

        return response()->json([
            'data' => [
                'results' => $this->searchService->search($validated['q'], $types),
            ],
        ]);
    }
}


