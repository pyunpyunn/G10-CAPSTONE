<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ExternalReportController extends Controller
{
    public function generate(Request $request, \App\Services\Web\ReportGenerationService $reports)
    {
        return $reports->generate($request);
    }

    public function status(string $token)
    {
        abort_unless(\Illuminate\Support\Str::isUuid($token), 404);
        $disk = Storage::disk('local');
        $path = 'report-jobs/'.$token.'.json';
        abort_unless($disk->exists($path), 404);
        return response()->json(json_decode($disk->get($path), true));
    }

    public function download(string $filename)
    {
        abort_unless(preg_match('/^[a-zA-Z0-9_-]+\.(pdf|csv|xlsx)$/D', $filename), 404);
        $disk = Storage::disk(config('reports.disk'));
        abort_unless($disk->exists('reports/'.$filename), 404);
        return $disk->download('reports/'.$filename);
    }
}
