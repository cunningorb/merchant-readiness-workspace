<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAssessmentAnswersRequest;
use App\Models\Assessment;
use App\Services\AssessmentQuestionCatalog;
use App\Services\CreateAssessmentService;
use App\Services\SaveAssessmentAnswersService;
use App\Services\SubmitAssessmentService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class AssessmentController extends Controller
{
    public function wizard(AssessmentQuestionCatalog $catalog): Response
    {
        return Inertia::render('Assessment/Wizard', [
            'catalog' => $catalog->sections(),
        ]);
    }

    public function store(CreateAssessmentService $service): JsonResponse
    {
        $assessment = $service->createAnonymousDraft();

        return response()->json([
            'assessment' => [
                'id' => $assessment->id,
                'status' => $assessment->status,
            ],
        ], 201);
    }

    public function answers(
        StoreAssessmentAnswersRequest $request,
        Assessment $assessment,
        SaveAssessmentAnswersService $service,
    ): JsonResponse {
        $assessment = $service->save($assessment, $request->validated('answers'));

        return response()->json([
            'assessment' => [
                'id' => $assessment->id,
                'status' => $assessment->status,
                'answers_count' => $assessment->answers->count(),
            ],
        ]);
    }

    public function submit(Assessment $assessment, SubmitAssessmentService $service): JsonResponse
    {
        $assessment = $service->submit($assessment);

        return response()->json([
            'assessment' => [
                'id' => $assessment->id,
                'status' => $assessment->status,
                'overall_score' => $assessment->overall_score,
                'overall_tier' => $assessment->overall_tier,
                'section_scores' => $assessment->section_scores,
            ],
            'recommendations' => $assessment->recommendations->map(fn ($recommendation) => [
                'title' => $recommendation->title,
                'description' => $recommendation->description,
                'category' => $recommendation->category,
                'priority' => $recommendation->priority,
                'expected_impact' => $recommendation->expected_impact,
            ]),
        ]);
    }
}
