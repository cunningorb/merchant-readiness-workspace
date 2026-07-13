<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AssessmentController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
});

Route::get('/privacy', function () {
    return Inertia::render('Legal/Privacy');
})->name('privacy');

Route::get('/terms', function () {
    return Inertia::render('Legal/Terms');
})->name('terms');

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'service' => 'merchant-readiness-workspace',
    ]);
});

Route::get('/assessment', [AssessmentController::class, 'wizard'])->name('assessment.wizard');

Route::get('/reports/{report:token}', [ReportController::class, 'show'])->name('reports.show');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [WorkspaceController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/assessments/{assessment}', [WorkspaceController::class, 'show'])->name('workspace.assessments.show');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
