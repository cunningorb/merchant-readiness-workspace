<?php

namespace App\Services;

use App\Models\Assessment;

class SaveAssessmentAnswersService
{
    public function __construct(private readonly AssessmentQuestionCatalog $catalog)
    {
    }

    public function save(Assessment $assessment, array $answers): Assessment
    {
        foreach ($answers as $answer) {
            $question = $this->catalog->question($answer['question_key']);

            $assessment->answers()->updateOrCreate(
                ['question_key' => $answer['question_key']],
                [
                    'section' => $question['section'],
                    'value' => $answer['value'],
                ],
            );
        }

        $this->syncMerchantIdentity($assessment, $answers);

        return $assessment->load('answers');
    }

    private function syncMerchantIdentity(Assessment $assessment, array $answers): void
    {
        $values = collect($answers)->pluck('value', 'question_key');

        $assessment->merchant->fill([
            'company_name' => $values->get('business.company_name', $assessment->merchant->company_name),
            'contact_email' => $values->get('business.contact_email', $assessment->merchant->contact_email),
        ])->save();
    }
}
