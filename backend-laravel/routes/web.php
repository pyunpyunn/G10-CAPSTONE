<?php

use Illuminate\Support\Facades\Route;

Route::get('/{path?}', function () {
    $frontendIndex = public_path('frontend-web/index.html');

    if (! file_exists($frontendIndex)) {
        return response(
            'React web build not found. Run `cd frontend-web && npm run build:laravel`, then refresh this page.',
            503,
            ['Content-Type' => 'text/plain; charset=UTF-8']
        );
    }

    return response()->file($frontendIndex);
})->where('path', '^(?!api(?:/|$)|frontend-web(?:/|$)|storage(?:/|$)|sanctum(?:/|$)|up$).*$');
