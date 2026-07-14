<?php

use App\Http\Controllers\AssessmentController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\WebsiteScanController;
use Illuminate\Support\Facades\Route;

Route::post('/assessments', [AssessmentController::class, 'store'])->middleware('throttle:20,1');
Route::post('/assessments/{assessment}/answers', [AssessmentController::class, 'answers'])->middleware('throttle:60,1');
Route::post('/assessments/{assessment}/website-scan', [WebsiteScanController::class, 'store'])->middleware('throttle:10,1');
Route::post('/assessments/{assessment}/submit', [AssessmentController::class, 'submit'])->middleware('throttle:20,1');
Route::get('/reports/{report:token}', [ReportController::class, 'apiShow']);
Route::post('/reports/{report:token}/contact', [ReportController::class, 'contact'])->middleware('throttle:10,1');

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
