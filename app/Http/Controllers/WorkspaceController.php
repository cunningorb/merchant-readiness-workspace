<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
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
}
