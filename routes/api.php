<?php

use App\Http\Controllers\AssessmentController;
use Illuminate\Support\Facades\Route;

Route::post('/assessments', [AssessmentController::class, 'store']);
Route::post('/assessments/{assessment}/answers', [AssessmentController::class, 'answers']);
