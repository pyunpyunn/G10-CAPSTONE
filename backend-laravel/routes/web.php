<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json([
    'application' => config('app.name'),
    'type' => 'api',
    'status' => 'ok',
]));

