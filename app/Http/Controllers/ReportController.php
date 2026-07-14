<?php

namespace App\Http\Controllers;

use App\Mail\ReportContactRequested;
use App\Models\Report;
use App\Services\AssessmentQuestionCatalog;
use App\Services\ReportBuilderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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

    public function contact(Report $report, ReportBuilderService $service): JsonResponse
    {
        abort_if($report->published_at === null, 404);

        $payload = $service->buildPayload($report);

        try {
            Mail::to('micah@normalview.pro')->send(new ReportContactRequested(
                companyName: $payload['merchant']['company_name'],
                contactEmail: $payload['merchant']['contact_email'] ?? null,
                reportUrl: $payload['url'],
            ));
        } catch (\Throwable $exception) {
            Log::warning('Failed to send report contact notification.', [
                'report_id' => $report->id,
                'exception' => $exception->getMessage(),
            ]);
        }

        return response()->json(['status' => 'queued']);
    }
}
