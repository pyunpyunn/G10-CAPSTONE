<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MappingSnapshotResource;
use App\Services\Web\MappingService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class MappingController extends Controller
{
    private MappingService $service;

    public function __construct(MappingService $service)
    {
        $this->service = $service;
    }

    public function overview(Request $request): JsonResponse
    {
        return $this->safeResponse(fn () => (new MappingSnapshotResource($this->service->overview($request)))->response($request));
    }

    /** Fast map shell: event, filters, boundaries, and summary only. */
    public function workspace(Request $request): JsonResponse
    {
        return $this->safeResponse(fn () => (new MappingSnapshotResource($this->service->workspace($request)))->response($request));
    }

    public function householdGeotags(Request $request): JsonResponse
    {
        return $this->safeResponse(fn () => response()->json(['data' => $this->service->householdGeotags($request)]));
    }

    public function evacuationSites(Request $request): JsonResponse
    {
        return $this->safeResponse(fn () => response()->json(['data' => $this->service->evacuationSites($request)]));
    }

    public function dispatchRoutes(Request $request): JsonResponse
    {
        return $this->safeResponse(fn () => response()->json(['data' => $this->service->dispatchRoutes($request)]));
    }

    private function safeResponse(callable $callback): JsonResponse
    {
        try {
            return $callback();
        } catch (QueryException $exception) {
            report($exception);
            return response()->json(['message' => 'Map data cannot be loaded because the database is not available right now.'], 503);
        } catch (Throwable $exception) {
            report($exception);
            return response()->json(['message' => 'Map data cannot be loaded right now. Please check the backend logs.'], 500);
        }
    }
}



