<?php

namespace App\Http\Requests;

use App\Services\AssessmentQuestionCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssessmentAnswersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(AssessmentQuestionCatalog $catalog): array
    {
        return [
            'answers' => ['required', 'array', 'min:1'],
            'answers.*.question_key' => ['required', 'string', Rule::in($catalog->questions()->pluck('key')->all())],
            'answers.*.value' => ['present'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $catalog = app(AssessmentQuestionCatalog::class);

            foreach ($this->input('answers', []) as $index => $answer) {
                $question = isset($answer['question_key']) ? $catalog->question($answer['question_key']) : null;

                if (! $question || ! array_key_exists('value', $answer)) {
                    continue;
                }

                $value = $answer['value'];

                if (($question['required'] ?? false) && ($value === null || $value === '' || $value === [])) {
                    $validator->errors()->add("answers.$index.value", 'This question is required.');
                }

                if (($question['type'] ?? null) === 'email' && $value !== null && ! filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $validator->errors()->add("answers.$index.value", 'This answer must be a valid email address.');
                }

                if (($question['type'] ?? null) === 'multiselect' && ! is_array($value)) {
                    $validator->errors()->add("answers.$index.value", 'This answer must be a list.');
                }

                if (($question['type'] ?? null) === 'boolean' && ! is_bool($value)) {
                    $validator->errors()->add("answers.$index.value", 'This answer must be true or false.');
                }

                if (isset($question['options']) && is_string($value) && ! in_array($value, $question['options'], true)) {
                    $validator->errors()->add("answers.$index.value", 'This answer is not one of the allowed options.');
                }
            }
        });
    }
}
