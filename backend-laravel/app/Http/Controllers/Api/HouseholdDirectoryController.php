<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Shared\SafeTrackHouseholdDirectory;
use App\Services\Shared\SafeTrackReportClient;
use App\Services\Shared\SafeTrackHouseholdSync;
use App\Services\Shared\SafeTrackApiException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Client\ConnectionException;

class HouseholdDirectoryController extends Controller
{
    public function index(Request $request, SafeTrackHouseholdDirectory $directory): JsonResponse
    {
        return response()->json(['data' => $directory->page($request)])->header('Cache-Control', 'no-store');
    }

    public function sync(Request $request, SafeTrackHouseholdDirectory $directory, SafeTrackReportClient $client, SafeTrackHouseholdSync $sync): JsonResponse
    {
        $sitioId = null;
        if ($request->isMethod('post') && $request->exists('sitio_id')) {
            $value = $request->input('sitio_id');
            if ((! is_string($value) && ! is_int($value)) || trim((string) $value) === '') {
                return response()->json(['message' => 'A valid sitio ID is required for a sitio-scoped sync.'], 422);
            }
            $sitioId = trim((string) $value);
        }

        try {
            $records = $client->households();
            if ($request->isMethod('get')) {
                $result = $directory->changes($records);
            } else {
                if ($sitioId !== null) {
                    $records = array_values(array_filter($records, static function (array $record) use ($sitioId): bool {
                        $location = $record['location'] ?? [];
                        $recordSitioId = is_array($location) ? ($location['sitio_id'] ?? $location['registered_sitio_id'] ?? null) : null;
                        return is_scalar($recordSitioId) && (string) $recordSitioId === $sitioId;
                    }));
                    if ($records === []) {
                        return response()->json(['message' => 'SafeTrack returned no households for the selected sitio. No data was changed.'], 404);
                    }
                }
                $result = $sync->sync($records, $directory, $sitioId);
            }
            return response()->json(['data' => $result])->header('Cache-Control', 'no-store');
        } catch (ConnectionException $error) {
            report($error);
            return response()->json(['message' => 'Cannot connect to SafeTrack. Check that its server is running, connected to Tailscale, and reachable on port 8000. Previously synced data has been retained.'], 503);
        } catch (SafeTrackApiException $error) {
            report($error);
            $message = match ($error->upstreamStatus) {
                401, 403 => 'SafeTrack rejected the configured API key.',
                404 => 'SafeTrack household API path was not found.',
                default => 'SafeTrack returned an unsuccessful or invalid household response.',
            };
            return response()->json(['message' => $message.' Previously synced data has been retained.'], 503);
        } catch (\Throwable $error) {
            report($error);
            return response()->json(['message' => 'SafeTrack sync is unavailable. Check its server, API key, and response format. Previously synced data has been retained.'], 503);
        }
    }
}
