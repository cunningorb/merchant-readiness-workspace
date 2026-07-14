<?php

namespace App\Services\WebsiteScan;

use App\Models\Assessment;
use App\Models\AssessmentAnswer;
use App\Models\AssessmentAnswerEvidence;
use App\Services\AssessmentQuestionCatalog;
use Illuminate\Support\Collection;

class ApplyWebsiteEvidenceToAnswersService
{
    public function __construct(private readonly AssessmentQuestionCatalog $catalog) {}

    public function clearPreviousWebsiteAnswers(Assessment $assessment): void
    {
        if ($assessment->status === 'submitted') {
            return;
        }

        $previousEvidence = $assessment->answerEvidence()
            ->where('source_type', 'website')
            ->get()
            ->groupBy('question_key');

        if ($previousEvidence->isEmpty()) {
            return;
        }

        $assessment->answers()
            ->whereIn('question_key', $previousEvidence->keys()->all())
            ->get()
            ->each(function (AssessmentAnswer $answer) use ($previousEvidence): void {
                $matchesPreviousWebsiteEvidence = $previousEvidence
                    ->get($answer->question_key, collect())
                    ->contains(fn (AssessmentAnswerEvidence $record): bool => $this->valuesMatch($answer->value, $record->value));

                if ($matchesPreviousWebsiteEvidence) {
                    $answer->delete();
                }
            });

        $assessment->answerEvidence()
            ->where('source_type', 'website')
            ->delete();
    }

    /**
     * @param  Collection<int, AssessmentAnswerEvidence>  $evidence
     */
    public function apply(Assessment $assessment, Collection $evidence): void
    {
        if ($assessment->status === 'submitted') {
            return;
        }

        $existingAnswerKeys = $assessment->answers()
            ->pluck('question_key')
            ->all();

        $evidence
            ->filter(fn (AssessmentAnswerEvidence $record): bool => ! in_array($record->question_key, $existingAnswerKeys, true))
            ->filter(fn (AssessmentAnswerEvidence $record): bool => ! $record->requires_confirmation)
            ->unique('question_key')
            ->each(function (AssessmentAnswerEvidence $record) use ($assessment): void {
                $question = $this->catalog->question($record->question_key);

                if ($question === null || $record->value === null) {
                    return;
                }

                AssessmentAnswer::create([
                    'assessment_id' => $assessment->id,
                    'question_key' => $record->question_key,
                    'section' => $question['section'],
                    'value' => $record->value,
                ]);
            });
    }

    private function valuesMatch(mixed $left, mixed $right): bool
    {
        return json_encode($left) === json_encode($right);
    }
}
