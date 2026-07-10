<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Services\AssessmentQuestionCatalog;
use App\Services\ReportBuilderService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function show(Report $report, AssessmentQuestionCatalog $catalog, ReportBuilderService $service): Response
    {
        abort_if($report->published_at === null, 404);

        return Inertia::render('Reports/Show', [
            'report' => $service->buildPayload($report),
            'catalog' => $catalog->sections(),
        ]);
    }

    public function apiShow(Report $report, ReportBuilderService $service): JsonResponse
    {
        abort_if($report->published_at === null, 404);

        return response()->json($service->buildPayload($report));
    }
}
