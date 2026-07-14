<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\AssessmentAnswer;

class SaveAssessmentAnswersService
{
    public function __construct(private readonly AssessmentQuestionCatalog $catalog)
    {
    }

    public function save(Assessment $assessment, array $answers): Assessment
    {
        if ($assessment->status === 'submitted') {
            abort(409, 'This assessment has already been submitted and cannot be edited.');
        }

        if ($answers === []) {
            return $assessment->load('answers');
        }

        $now = now();

        AssessmentAnswer::upsert(
            collect($answers)->map(function (array $answer) use ($assessment, $now): array {
                $question = $this->catalog->question($answer['question_key']);

                return [
                    'assessment_id' => $assessment->id,
                    'question_key' => $answer['question_key'],
                    'section' => $question['section'],
                    'value' => json_encode($answer['value'], JSON_THROW_ON_ERROR),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            })->all(),
            ['assessment_id', 'question_key'],
            ['section', 'value', 'updated_at'],
        );

        $this->syncMerchantIdentity($assessment, $answers);

        return $assessment->load('answers');
    }

    private function syncMerchantIdentity(Assessment $assessment, array $answers): void
    {
        $values = collect($answers)->pluck('value', 'question_key');
        $merchantAttributes = $this->catalog->questions()
            ->filter(fn (array $question): bool => isset($question['merchant_field']))
            ->mapWithKeys(fn (array $question): array => [$question['merchant_field'] => $values->get($question['key'])])
            ->filter(fn ($value): bool => is_string($value) && $value !== '')
            ->all();

        if ($merchantAttributes !== []) {
            $assessment->merchant->fill($merchantAttributes)->save();
        }
    }
}
