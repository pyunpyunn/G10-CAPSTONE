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
