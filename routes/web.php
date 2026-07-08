<?php

use Inertia\Inertia;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return Inertia::render('Welcome');
});

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'service' => 'merchant-readiness-workspace',
    ]);
});
