<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Shared\EvaTrackReportClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EvaTrackReportController extends Controller
{
    public function configuration(EvaTrackReportClient $client): JsonResponse
    {
        return response()->json(['data' => $client->configuration()])->header('Cache-Control', 'no-store');
    }

    public function providers(EvaTrackReportClient $client): JsonResponse
    {
        return response()->json(['data' => ['providers' => [
            array_merge($client->configuration(), ['key'=>'evatrack', 'name'=>'EvaTrack', 'implemented'=>true]),
            ['key'=>'safetrack', 'name'=>'SafeTrack', 'implemented'=>false, 'configured'=>false, 'report_types'=>[], 'formats'=>[]],
            ['key'=>'trackingaid', 'name'=>'TrackingAid', 'implemented'=>false, 'configured'=>false, 'report_types'=>[], 'formats'=>[]],
        ]]] )->header('Cache-Control', 'no-store');
    }

    public function generate(Request $request, EvaTrackReportClient $client): JsonResponse
    {
        $rules = [
            'report_type' => 'required|string|in:demographics,dromic,utilization,vulnerable,general',
            'format' => 'nullable|string|in:pdf,csv,xlsx',
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => ['nullable', 'date_format:Y-m-d'],
        ];
        if ($request->filled('start_date')) $rules['end_date'][] = 'after_or_equal:start_date';
        return $client->generate(array_filter($request->validate($rules), fn ($value) => $value !== null));
    }
}
