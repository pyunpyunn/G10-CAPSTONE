<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MobileLoginRequest;
use App\Services\Navigation\MobileNavigationService;
use App\Services\Navigation\MobileSyncService;
use App\Services\Shared\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use App\Services\Navigation\RoutePrecomputeService;
use App\Services\Shared\ValhallaRoutingService;
use Illuminate\Support\Facades\DB;

class MobileNavigationController extends Controller
{
    public function evacuationCenterRoadRoute(Request $request, string $centerId, ValhallaRoutingService $routing): JsonResponse
    {
        $origin = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'profile' => ['nullable', 'in:auto,pedestrian'],
        ]);
        abort_unless(DB::table('responders')->where('user_id', $request->user()->user_id)->exists(), 403, 'This account has no rescuer profile.');

        $center = DB::table('evacuation_centers')
            ->where('evacuation_center_id', $centerId)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->first(['evacuation_center_id', 'name', 'latitude', 'longitude']);
        abort_unless($center, 404, 'This evacuation center is unavailable or has no mapped location.');

        try {
            $route = $routing->routeBetween(
                $origin,
                ['latitude' => (float) $center->latitude, 'longitude' => (float) $center->longitude],
                $origin['profile'] ?? 'auto',
            );
        } catch (\Throwable $exception) {
            report($exception);
            abort(503, 'Road routing is unavailable. Keep the map open and retry when the connection improves.');
        }
        abort_unless($route, 422, 'No road route is available to this evacuation center.');

        return response()->json(['data' => $route + [
            'evacuation_center_id' => $center->evacuation_center_id,
            'destination_label' => $center->name,
            'status' => 'ready',
        ]]);
    }

    public function assignedRoadRoute(Request $request, int $assignmentId): JsonResponse
    {
        $payload = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'profile' => ['required', 'in:auto,pedestrian'],
            'household_id' => ['required', 'string'],
        ]);
        $responderId = (int) DB::table('responders')->where('user_id', $request->user()->user_id)->value('responder_id');
        $query = app(\App\Queries\RescuerAssignmentQuery::class);
        $assignment = $query->find($assignmentId, $responderId);
        abort_unless($assignment, 404, 'This dispatch is not assigned to your account.');
        abort_if(in_array($assignment->status, ['completed', 'cancelled'], true), 409, 'This dispatch is closed.');
        abort_unless((string)$assignment->household_id === (string)$payload['household_id'], 403, 'Only your current queued household can be routed.');
        if ($payload['profile'] === 'pedestrian') {
            $target = DB::table('geotagged_locations')->where('household_id', $payload['household_id'])
                ->whereNotNull('latitude')->whereNotNull('longitude')->orderByDesc('updated_at')->orderByDesc('location_id')->first();
            abort_unless($target, 404, 'The household has no location pin.');
            try {
                $route = app(ValhallaRoutingService::class)->routeBetween($payload, (array) $target, 'pedestrian');
            } catch (\Throwable) {
                abort(503, 'Walking directions are unavailable. Keep the saved route or choose vehicle directions.');
            }
            abort_unless($route, 422, 'No walking route is available.');
            return response()->json(['data' => $route + ['household_id' => $payload['household_id'], 'status' => 'ready']]);
        }
        app(\App\Services\Mobile\RescuerRouteWriter::class)->refreshPlannedRoadRoute($assignmentId, $payload + ['responder_id' => $responderId]);
        $route = $query->routeForAssignment($assignmentId, $responderId);
        abort_unless($route && $route->route_polyline, 503, 'Road routing is unavailable. Retry while connected; keep any saved route.');
        $data = (new \App\Http\Resources\RescuerMobileRouteResource($route))->resolve($request);
        return response()->json(['data' => $data + ['profile' => 'auto', 'status' => 'ready',
            'distance_m' => (float) $data['distance_km'] * 1000, 'duration_s' => (float) $data['duration_min'] * 60]]);
    }

    public function freshRoute(Request $request, int $id, RoutePrecomputeService $routes, ValhallaRoutingService $routing): JsonResponse
    {
        $payload = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'profile' => ['required', 'in:auto,pedestrian'],
            'household_id' => ['required', 'string'],
        ]);
        $teamId = (int) $request->attributes->get('mobile_device')->team_id;
        abort_unless(DB::table('responder_assignments')->where('assignment_id', $id)->where('team_id', $teamId)->exists(), 403);
        abort_unless($routes->assignedHouseholdIds($teamId)->contains($payload['household_id']), 403);
        $target = DB::table('geotagged_locations')->where('household_id', $payload['household_id'])
            ->whereNotNull('latitude')->whereNotNull('longitude')->orderByDesc('updated_at')->first();
        abort_unless($target, 404, 'The destination has no location pin.');
        $route = $routing->routeBetween($payload, (array) $target, $payload['profile']);
        abort_unless($route, 422, 'No road route is available.');

        return response()->json(['data' => array_merge($route, ['household_id' => $payload['household_id'], 'status' => 'ready'])]);
    }

    public function login(MobileLoginRequest $request, AuthService $auth): JsonResponse
    {
        return $auth->mobileLogin($request);
    }

    public function bootstrap(Request $request, MobileNavigationService $navigation): Response
    {
        return $navigation->etagResponse($request, $navigation->bootstrap($request));
    }

    public function packManifest(string $version, MobileNavigationService $navigation): JsonResponse
    {
        return response()->json(['data' => $navigation->packManifest($version)]);
    }

    public function sync(Request $request, MobileSyncService $sync): JsonResponse
    {
        return response()->json(['data' => $sync->sync($request)]);
    }

    public function downloadPack(string $version): BinaryFileResponse
    {
        abort_unless(preg_match('/^[A-Za-z0-9._-]{1,64}$/', $version) === 1, 404);
        $pack = DB::table('offline_packs')->where('version', $version)->first();
        abort_unless($pack, 404);
        $extension = ($pack->pack_format ?? 'mbtiles_zip') === 'maplibre_db' ? '.db' : '.zip';
        $path = rtrim((string) config('navigation.pack_root'), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$version.$extension;
        abort_unless(is_file($path), 404, 'Offline map pack file was not found.');

        return response()->download($path, $version.$extension, [
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }
}
