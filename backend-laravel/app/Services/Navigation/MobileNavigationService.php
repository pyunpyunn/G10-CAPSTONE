<?php

namespace App\Services\Navigation;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

class MobileNavigationService
{
    public function __construct(private RoutePrecomputeService $routes) {}

    public function bootstrap(Request $request): array
    {
        $validated = $request->validate(['since' => ['nullable', 'date']]);
        $since = isset($validated['since']) ? CarbonImmutable::parse($validated['since'])->format('Y-m-d H:i:s') : null;
        $device = $request->attributes->get('mobile_device');
        $teamId = (int) $device->team_id;
        $snapshotAt = CarbonImmutable::now()->startOfSecond();
        $team = DB::table('rescue_teams')->where('team_id', $teamId)->first(['team_id', 'team_name', 'team_code', 'team_type', 'updated_at']);
        if (! $team) {
            throw ValidationException::withMessages(['team' => ['The rescuer team is no longer available.']]);
        }

        $householdIds = $this->routes->assignedHouseholdIds($teamId);
        $assignments = DB::table('responder_assignments')
            ->where('team_id', $teamId)
            ->when($since !== null, fn ($query) => $query->where('updated_at', '>=', $since))
            ->orderByDesc('assigned_at')
            ->limit(250)
            ->get(['assignment_id', 'assignment_code', 'disaster_id', 'dispatch_type', 'household_id', 'assigned_area', 'route_notes', 'status', 'assigned_at', 'updated_at'])
            ->map(function ($assignment) {
                $notes = json_decode((string) $assignment->route_notes, true) ?: [];
                $assignment->household_ids = array_values(array_unique(array_filter([
                    $assignment->household_id, ...($notes['household_ids'] ?? []),
                    ...array_merge([], ...array_values($notes['responder_households'] ?? [])),
                ])));
                unset($assignment->route_notes);
                return $assignment;
            });

        $households = DB::table('households as household')
            ->whereIn('household.household_id', $householdIds->all())
            ->whereNull('household.deleted_at')
            ->when($since !== null, fn ($query) => $query->where('household.updated_at', '>=', $since))
            ->orderBy('household.household_id')
            ->get(['household.household_id', 'household.household_code', 'household.household_name', 'household.member_count', 'household.updated_at']);

        $geotags = DB::table('geotagged_locations')
            ->whereIn('household_id', $householdIds->all())
            ->whereNotNull('latitude')->whereNotNull('longitude')
            ->when($since !== null, fn ($query) => $query->where('updated_at', '>=', $since))
            ->orderByDesc('updated_at')->orderByDesc('location_id')
            ->get(['household_id', 'latitude', 'longitude', 'location_label', 'accuracy_m', 'is_verified', 'updated_at'])
            ->unique('household_id')->values();

        $routeQuery = DB::table('cached_routes')->where('team_id', $teamId)
            ->where(function ($query) use ($householdIds): void {
                $query->whereIn('household_id', $householdIds->all())->orWhereNotNull('evac_center_id');
            });
        if ($since !== null) {
            $routeQuery->where('updated_at', '>=', $since);
        }
        $routes = $routeQuery->orderBy('target_key')->orderBy('profile')
            ->get(['team_id', 'household_id', 'evac_center_id', 'profile', 'polyline6', 'steps', 'distance_m', 'duration_s', 'status', 'failure_reason', 'computed_at']);

        $centerQuery = DB::table('evacuation_centers')->where('status', 'active')
            ->whereNull('deleted_at');
        if ($since !== null) {
            $centerQuery->where('updated_at', '>=', $since);
        }
        $centers = $centerQuery->orderBy('name')
            ->get(['evacuation_center_id', 'name', 'center_type', 'latitude', 'longitude', 'capacity', 'current_occupancy', 'status', 'updated_at']);

        $pack = DB::table('offline_packs')->where('is_active', true)->orderByDesc('version')->first();
        $packManifest = $pack ? [
            'version' => $pack->version,
            'pack_format' => $pack->pack_format ?? 'mbtiles_zip',
            'bbox' => json_decode($pack->bbox, true),
            'style_url' => $pack->style_url,
            'size_bytes' => (int) $pack->size_bytes,
            'checksum' => $pack->checksum,
            'download_url' => URL::temporarySignedRoute(
                'mobile.packs.download',
                now()->addHours(24),
                ['version' => $pack->version],
            ),
        ] : null;

        return [
            'team' => $team,
            'status_options' => DB::table('household_statuses')->where('is_active', true)->orderBy('sort_order')
                ->get(['status_id', 'status_key', 'status_label']),
            'dispatches' => $assignments,
            'households' => $households,
            'geotags' => $geotags,
            'routes' => $routes,
            'evacuation_centers' => $centers,
            'pack' => $packManifest,
            'full_snapshot' => $since === null,
            'cursor' => $snapshotAt->toDateTimeString(),
            'server_time' => now()->toISOString(),
        ];
    }

    public function etagResponse(Request $request, array $payload)
    {
        $etagPayload = $payload;
        unset($etagPayload['server_time'], $etagPayload['cursor']);
        if (isset($etagPayload['pack']) && is_array($etagPayload['pack'])) {
            unset($etagPayload['pack']['download_url']);
        }
        $etag = '"'.hash('sha256', json_encode($etagPayload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)).'"';
        if (trim((string) $request->header('If-None-Match')) === $etag) {
            return response('', 304)->header('ETag', $etag);
        }

        return response()->json(['data' => $payload])->header('ETag', $etag);
    }

    public function packManifest(string $version): array
    {
        $pack = DB::table('offline_packs')->where('version', $version)->where('is_active', true)->first();
        if (! $pack) {
            abort(404, 'Offline map pack version was not found.');
        }

        return [
            'version' => $pack->version,
            'pack_format' => $pack->pack_format ?? 'mbtiles_zip',
            'bbox' => json_decode($pack->bbox, true),
            'style_url' => $pack->style_url,
            'size_bytes' => (int) $pack->size_bytes,
            'checksum' => $pack->checksum,
            'download_url' => URL::temporarySignedRoute(
                'mobile.packs.download',
                now()->addHours(24),
                ['version' => $pack->version],
            ),
        ];
    }
}
