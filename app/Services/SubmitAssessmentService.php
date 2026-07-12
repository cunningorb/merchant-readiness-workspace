<?php

namespace App\Services;

use App\Contracts\AssessmentScorer;
use App\Models\Assessment;
use App\Services\Benchmarks\BenchmarkComparisonService;
use App\Services\Opportunities\OpportunityCalculationService;
use App\Services\Opportunities\OpportunityRankingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubmitAssessmentService
{
    public function __construct(
        private readonly AssessmentQuestionCatalog $catalog,
        private readonly AssessmentScorer $scorer,
        private readonly RecommendationEngine $recommendations,
        private readonly ReportBuilderService $reports,
        private readonly OpportunityCalculationService $opportunityCalculator,
        private readonly OpportunityRankingService $opportunityRanker,
        private readonly BenchmarkComparisonService $benchmarkComparator,
    ) {}

    public function submit(Assessment $assessment): Assessment
    {
        if ($assessment->status === 'submitted') {
            abort(409, 'This assessment has already been submitted.');
        }

        $assessment->loadMissing('answers');

        $missing = $this->catalog->questions()
            ->filter(fn (array $question) => $question['required'] ?? false)
            ->reject(fn (array $question) => $this->isAnswered($assessment, $question['key']))
            ->pluck('key');

        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages(
                $missing->mapWithKeys(fn (string $key) => [$key => ['This question is required.']])->all()
            );
        }

        $scores = $this->scorer->score($assessment);

        DB::transaction(function () use ($assessment, $scores) {
            $this->recommendations->generate($assessment, $scores)->each(
                fn ($draft) => $assessment->recommendations()->create([
                    'title' => $draft->title,
                    'description' => $draft->description,
                    'category' => $draft->category,
                    'priority' => $draft->priority,
                    'expected_impact' => $draft->expectedImpact,
                ])
            );

            $opportunities = $this->opportunityRanker->rankOpportunities(
                $this->opportunityCalculator->calculate($assessment)
            );

            foreach ($opportunities as $opportunity) {
                $opportunity->save();
            }

            $benchmarkComparisons = $this->benchmarkComparator->compare($assessment);

            foreach ($benchmarkComparisons as $index => $comparison) {
                $assessment->benchmarkComparisons()->create([
                    'benchmark_set_id' => $comparison->benchmarkSetId,
                    'metric_key' => $comparison->metricKey,
                    'label' => $comparison->label,
                    'merchant_value' => $comparison->merchantValue,
                    'minimum_value' => $comparison->range->minimum,
                    'maximum_value' => $comparison->range->maximum,
                    'unit' => $comparison->range->unit,
                    'interpretation' => $comparison->interpretation,
                    'source_type' => $comparison->sourceType->value,
                    'source_label' => $comparison->sourceLabel,
                    'methodology' => $comparison->methodology,
                    'benchmark_version' => $comparison->benchmarkVersion,
                    'sort_order' => $index,
                ]);
            }

            $assessment->forceFill([
                'overall_score' => $scores->overallScore,
                'overall_tier' => $scores->overallTier,
                'section_scores' => $scores->sections,
                'status' => 'submitted',
                'submitted_at' => now(),
            ])->save();

            $this->reports->createForAssessment($assessment);
        });

        return $assessment->fresh(['answers', 'recommendations', 'report', 'opportunities', 'benchmarkComparisons']);
    }

    private function isAnswered(Assessment $assessment, string $questionKey): bool
    {
        $value = $assessment->answerValue($questionKey);

        return $value !== null && $value !== '' && $value !== [];
    }
}
