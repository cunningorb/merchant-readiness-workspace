<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\Recommendation;
use App\Services\AssessmentQuestionCatalog;
use App\Services\ReportBuilderService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceController extends Controller
{
    public function index(Request $request): Response
    {
        $sort = in_array($request->query('sort'), ['company', 'tier', 'submitted_at'], true)
            ? $request->query('sort')
            : 'submitted_at';
        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';

        $query = Assessment::query()
            ->with('merchant')
            ->where('status', 'submitted')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = '%'.$request->query('search').'%';

                $query->whereHas('merchant', function ($merchantQuery) use ($search) {
                    $merchantQuery->where('company_name', 'like', $search)
                        ->orWhere('contact_name', 'like', $search);
                });
            });

        match ($sort) {
            'company' => $query->join('merchants', 'merchants.id', '=', 'assessments.merchant_id')
                ->orderBy('merchants.company_name', $direction)
                ->select('assessments.*'),
            'tier' => $query->orderBy('overall_score', $direction),
            default => $query->orderBy('submitted_at', $direction),
        };

        $assessments = $query->paginate(15)->withQueryString();

        return Inertia::render('Workspace/Index', [
            'assessments' => $assessments,
            'filters' => [
                'search' => $request->query('search'),
                'sort' => $sort,
                'direction' => $direction,
            ],
        ]);
    }

    public function show(Assessment $assessment, AssessmentQuestionCatalog $catalog, ReportBuilderService $service): Response
    {
        abort_if($assessment->status !== 'submitted', 404);

        $assessment->loadMissing(['merchant', 'recommendations', 'report']);

        $report = $assessment->report ?? $service->createForAssessment($assessment);
        $payload = $service->buildPayload($report);
        $payload['merchant']['contact_name'] = $assessment->merchant->contact_name;
        $payload['merchant']['contact_email'] = $assessment->merchant->contact_email;
        $payload['merchant']['website'] = $assessment->merchant->website;
        $payload['submitted_at'] = $assessment->submitted_at;
        $payload['talking_points'] = $assessment->recommendations
            ->sortBy('id')
            ->sortBy(fn (Recommendation $recommendation) => match ($recommendation->priority) {
                'high' => 0,
                'medium' => 1,
                'low' => 2,
                default => 3,
            })
            ->take(3)
            ->values()
            ->map(fn (Recommendation $recommendation) => [
                'title' => $recommendation->title,
                'description' => $recommendation->description,
                'expected_impact' => $recommendation->expected_impact,
            ])
            ->all();

        return Inertia::render('Workspace/Show', [
            'report' => $payload,
            'catalog' => $catalog->sections(),
        ]);
    }
}
