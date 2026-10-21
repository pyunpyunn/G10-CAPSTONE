<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Shared\EvaTrackReportClient;
use App\Services\Shared\SafeTrackReportClient;
use App\Services\Shared\TrackingAidReportClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EvaTrackReportController extends Controller
{
    public function configuration(EvaTrackReportClient $client): JsonResponse
    {
        return response()->json(['data' => $client->configuration()])->header('Cache-Control', 'no-store');
    }

    public function providers(EvaTrackReportClient $client, SafeTrackReportClient $safeTrack, TrackingAidReportClient $trackingAid): JsonResponse
    {
        return response()->json(['data' => ['providers' => [
            array_merge($client->configuration(), ['key'=>'evatrack', 'name'=>'EvaTrack', 'implemented'=>true]),
            array_merge($safeTrack->configuration(), ['key'=>'safetrack', 'name'=>'SafeTrack', 'implemented'=>true]),
            array_merge($trackingAid->configuration(), ['key'=>'trackingaid', 'name'=>'TrackingAid', 'implemented'=>true]),
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

    public function generateSafeTrack(Request $request, SafeTrackReportClient $client): JsonResponse
    {
        $rules = [
            'report_type' => 'required|string|in:households,vulnerable_households,household_detail,members,pwd_members,senior_members,pregnant_members,sitios',
            'format' => 'nullable|string|in:pdf,csv,xlsx',
            'sitio_id' => 'nullable|integer|min:1',
            'household_id' => 'nullable|string|max:120',
            'search' => 'nullable|string|max:160',
        ];
        if ($request->input('report_type') === 'household_detail') {
            $rules['household_id'] = 'required|string|max:120';
        }

        return $client->generate(array_filter($request->validate($rules), fn ($value) => $value !== null));
    }

    public function generateTrackingAid(Request $request, TrackingAidReportClient $client): JsonResponse
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
