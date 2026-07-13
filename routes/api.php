<?php

use App\Http\Controllers\AssessmentController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::post('/assessments', [AssessmentController::class, 'store']);
Route::post('/assessments/{assessment}/answers', [AssessmentController::class, 'answers']);
Route::post('/assessments/{assessment}/submit', [AssessmentController::class, 'submit']);
Route::get('/reports/{report:token}', [ReportController::class, 'apiShow']);

// These endpoints are anonymous (consistent with the anonymous-assessment
// pattern) but, unlike answer-saving, accept file uploads — an unbounded
// disk-write surface. Rate-limit them so an anonymous client cannot loop
// create-import -> attach large files without limit.
Route::middleware('throttle:20,1')->group(function () {
    Route::post('/assessments/{assessment}/imports', [ImportController::class, 'store']);
    Route::post('/assessments/{assessment}/imports/{import}/files', [ImportController::class, 'storeFile']);
    Route::post('/assessments/{assessment}/imports/{import}/process', [ImportController::class, 'process']);
    Route::post('/assessments/{assessment}/imports/{import}/cancel', [ImportController::class, 'cancel']);
    Route::get('/assessments/{assessment}/imports/{import}', [ImportController::class, 'show']);
});
