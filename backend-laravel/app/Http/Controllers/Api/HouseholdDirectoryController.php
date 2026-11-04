<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Shared\SafeTrackHouseholdDirectory;
use App\Services\Shared\SafeTrackReportClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HouseholdDirectoryController extends Controller
{
    public function index(Request $request, SafeTrackHouseholdDirectory $directory): JsonResponse
    {
        return response()->json(['data' => $directory->page($request)])->header('Cache-Control', 'no-store');
    }

    public function sync(Request $request, SafeTrackHouseholdDirectory $directory, SafeTrackReportClient $client): JsonResponse
    {
        try {
            $records = $client->households();
            $result = $request->isMethod('get') ? $directory->changes($records) : $directory->sync($records);
            return response()->json(['data' => $result])->header('Cache-Control', 'no-store');
        } catch (\Throwable $error) {
            report($error);
            return response()->json(['message' => 'SafeTrack sync is unavailable. Check its server, API key, and response format. Previously synced data has been retained.'], 503);
        }
    }
}
