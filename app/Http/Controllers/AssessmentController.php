<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAssessmentAnswersRequest;
use App\Models\Assessment;
use App\Services\AssessmentQuestionCatalog;
use App\Services\CreateAssessmentService;
use App\Services\SaveAssessmentAnswersService;
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
}
