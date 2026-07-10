<?php

use App\Http\Controllers\AssessmentController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::post('/assessments', [AssessmentController::class, 'store']);
Route::post('/assessments/{assessment}/answers', [AssessmentController::class, 'answers']);
Route::post('/assessments/{assessment}/submit', [AssessmentController::class, 'submit']);
Route::get('/reports/{report:token}', [ReportController::class, 'apiShow']);
