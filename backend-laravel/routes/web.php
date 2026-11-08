<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', fn () => response()->json([
    'application' => config('app.name'),
    'type' => 'api',
    'status' => 'ok',
]));

Route::match(['GET', 'HEAD', 'OPTIONS'], '/storage/{path}', function (string $path) {
    if (request()->isMethod('OPTIONS')) {
        return response('', 204, [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, HEAD, OPTIONS',
            'Access-Control-Allow-Headers' => 'Range, Accept, Content-Type, Authorization',
        ]);
    }

    $cleanPath = ltrim(str_replace('..', '', $path), '/');

    if (! Storage::disk('public')->exists($cleanPath)) {
        abort(404);
    }

    $fullPath = Storage::disk('public')->path($cleanPath);
    $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
    $mimeMap = [
        'webm' => 'audio/webm',
        'm4a' => 'audio/mp4',
        'mp4' => 'audio/mp4',
        'aac' => 'audio/aac',
        'mp3' => 'audio/mpeg',
        'ogg' => 'audio/ogg',
        'wav' => 'audio/wav',
    ];

    $headers = [
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Methods' => 'GET, HEAD, OPTIONS',
        'Access-Control-Allow-Headers' => 'Range, Accept, Content-Type, Authorization',
        'Access-Control-Expose-Headers' => 'Content-Length, Content-Range, Accept-Ranges',
        'Accept-Ranges' => 'bytes',
        'Cache-Control' => 'no-store, no-cache, must-revalidate',
    ];

    if (isset($mimeMap[$ext])) {
        $headers['Content-Type'] = $mimeMap[$ext];
    }

    $magic = @file_get_contents($fullPath, false, null, 0, 4);
    if ($magic === 'RIFF') {
        $headers['Content-Type'] = 'audio/wav';
    }

    return response()->file($fullPath, $headers);
})->where('path', '.*');


