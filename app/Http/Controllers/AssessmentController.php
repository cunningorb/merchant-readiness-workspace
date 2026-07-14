<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAssessmentAnswersRequest;
use App\Models\Assessment;
use App\Services\AssessmentQuestionCatalog;
use App\Services\CreateAssessmentService;
use App\Services\ReportBuilderService;
use App\Services\SaveAssessmentAnswersService;
use App\Services\SendAssessmentReportEmailService;
use App\Services\SubmitAssessmentService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class AssessmentController extends Controller
{
    public function wizard(AssessmentQuestionCatalog $catalog, ?Assessment $assessment = null): Response
    {
        $assessment?->load(['answers', 'answerEvidence', 'merchant']);

        return Inertia::render('Assessment/Wizard', [
            'catalog' => $catalog->sections(),
            'initialAssessment' => $assessment ? [
                'id' => $assessment->id,
                'status' => $assessment->status,
                'resume_url' => route('assessment.resume', $assessment),
                'answers' => $assessment->answers
                    ->map(fn ($answer) => [
                        'question_key' => $answer->question_key,
                        'section' => $answer->section,
                        'value' => $answer->value,
                    ])
                    ->values()
                    ->all(),
                'evidence' => $assessment->answerEvidence
                    ->groupBy('question_key')
                    ->map(fn ($records) => $records->values()->map(fn ($record) => [
                        'question_key' => $record->question_key,
                        'source_type' => $record->source_type,
                        'source_label' => $record->source_label,
                        'confidence' => $record->confidence,
                        'value' => $record->value,
                        'evidence_url' => $record->evidence_url,
                        'evidence_snippet' => $record->evidence_snippet,
                        'observed_period_start' => $record->observed_period_start?->toDateString(),
                        'observed_period_end' => $record->observed_period_end?->toDateString(),
                        'metadata' => $record->metadata ?? [],
                    ])->all())
                    ->all(),
                'merchant' => [
                    'website' => $assessment->merchant?->website,
                ],
            ] : null,
        ]);
    }

    public function store(CreateAssessmentService $service): JsonResponse
    {
        $assessment = $service->createAnonymousDraft();

        return response()->json([
            'assessment' => [
                'id' => $assessment->id,
                'status' => $assessment->status,
                'resume_url' => route('assessment.resume', $assessment),
            ],
        ], 201);
    }

    public function answers(
        StoreAssessmentAnswersRequest $request,
        Assessment $assessment,
        SaveAssessmentAnswersService $service,
    ): JsonResponse {
        $assessment = $service->save($assessment, $request->draftAnswers());

        return response()->json([
            'assessment' => [
                'id' => $assessment->id,
                'status' => $assessment->status,
                'answers_count' => $assessment->answers->count(),
            ],
        ]);
    }

    public function submit(
        Assessment $assessment,
        SubmitAssessmentService $service,
        ReportBuilderService $reports,
        SendAssessmentReportEmailService $reportEmail,
    ): JsonResponse {
        $assessment = $service->submit($assessment);
        $assessment->load('merchant');
        // Insight generation is skipped here so submit stays fast; the report
        // page generates (and caches) it on first view instead.
        $reportPayload = $reports->buildPayload($assessment->report, includeInsight: false);
        $reportEmail->send($assessment);

        return response()->json([
            'assessment' => [
                'id' => $assessment->id,
                'status' => $assessment->status,
                'overall_score' => $assessment->overall_score,
                'overall_tier' => $assessment->overall_tier,
                'section_scores' => $assessment->section_scores,
                'ranked_sections' => collect($assessment->section_scores)->sortBy('score')->all(),
            ],
            'merchant' => [
                'company_name' => $assessment->answerValue('business.company_name')
                    ?? $assessment->merchant->company_name,
                'contact_email' => $assessment->answerValue('business.contact_email')
                    ?? $assessment->merchant->contact_email,
            ],
            'recommendations' => $assessment->recommendations->map(fn ($recommendation) => [
                'title' => $recommendation->title,
                'description' => $recommendation->description,
                'category' => $recommendation->category,
                'priority' => $recommendation->priority,
                'expected_impact' => $recommendation->expected_impact,
            ]),
            'report' => [
                'token' => $assessment->report->token,
                'url' => route('reports.show', $assessment->report->token),
                'payload' => $reportPayload,
            ],
        ]);
    }
}
