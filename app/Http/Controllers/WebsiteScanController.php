<?php

namespace App\Http\Controllers;

use App\Http\Requests\StartWebsiteScanRequest;
use App\Models\Assessment;
use App\Services\WebsiteScan\StartWebsiteScanService;
use Illuminate\Http\JsonResponse;

class WebsiteScanController extends Controller
{
    public function store(StartWebsiteScanRequest $request, Assessment $assessment, StartWebsiteScanService $service): JsonResponse
    {
        $scan = $service->start($assessment, $request->validated('url'), $request->ip());
        $assessment->load(['answerEvidence', 'answers']);

        return response()->json([
            'website_scan' => [
                'id' => $scan->id,
                'status' => $scan->status,
                'url' => $scan->url,
                'pages_scanned' => $scan->pages_scanned,
                'extracted_data' => $scan->extracted_data ?? [],
            ],
            'evidence' => $assessment->answerEvidence
                ->groupBy('question_key')
                ->map(fn ($records) => $records->values()->map(fn ($record) => [
                    'question_key' => $record->question_key,
                    'source_type' => $record->source_type,
                    'source_label' => $record->source_label,
                    'provider' => $record->provider,
                    'model' => $record->model,
                    'confidence' => $record->confidence,
                    'requires_confirmation' => $record->requires_confirmation,
                    'value' => $record->value,
                    'evidence_url' => $record->evidence_url,
                    'evidence_snippet' => $record->evidence_snippet,
                    'observed_period_start' => $record->observed_period_start?->toDateString(),
                    'observed_period_end' => $record->observed_period_end?->toDateString(),
                    'metadata' => $record->metadata ?? [],
                ])->all())
                ->all(),
            'answers' => $assessment->answers
                ->map(fn ($answer) => [
                    'question_key' => $answer->question_key,
                    'section' => $answer->section,
                    'value' => $answer->value,
                ])
                ->values()
                ->all(),
            'merchant' => [
                'website' => $assessment->merchant()->value('website'),
            ],
        ], 201);
    }
}
