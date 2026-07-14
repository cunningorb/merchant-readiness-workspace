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
            'answers' => ['required', 'array'],
            'answers.*.question_key' => ['required', 'string', Rule::in($catalog->questions()->pluck('key')->all())],
            'answers.*.value' => ['present'],
        ];
    }

    public function draftAnswers(): array
    {
        $catalog = app(AssessmentQuestionCatalog::class);

        return collect($this->validated('answers'))
            ->filter(function (mixed $answer) use ($catalog): bool {
                if (! is_array($answer)) {
                    return true;
                }

                $question = isset($answer['question_key']) ? $catalog->question($answer['question_key']) : null;

                if ($question === null || ! array_key_exists('value', $answer)) {
                    return true;
                }

                return ! $this->isBlankDraftValue($answer['value']);
            })
            ->values()
            ->all();
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

                if ($this->isBlankDraftValue($value)) {
                    continue;
                }

                if (($question['required'] ?? false) && ($value === null || $value === '' || $value === [])) {
                    $validator->errors()->add("answers.$index.value", 'This question is required.');
                }

                if ($value === null) {
                    $validator->errors()->add("answers.$index.value", 'This answer cannot be empty.');
                    continue;
                }

                match ($question['type'] ?? null) {
                    'text' => $this->validateStringAnswer($validator, $index, $value),
                    'email' => $this->validateEmailAnswer($validator, $index, $value),
                    'select' => $this->validateSelectAnswer($validator, $index, $value, $question['options'] ?? []),
                    'multiselect' => $this->validateMultiselectAnswer($validator, $index, $value, $question['options'] ?? []),
                    'boolean' => $this->validateBooleanAnswer($validator, $index, $value),
                    default => null,
                };
            }
        });
    }

    private function validateStringAnswer($validator, int $index, mixed $value): void
    {
        if (! is_string($value)) {
            $validator->errors()->add("answers.$index.value", 'This answer must be text.');
        }
    }

    private function validateEmailAnswer($validator, int $index, mixed $value): void
    {
        if (! is_string($value) || ! filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $validator->errors()->add("answers.$index.value", 'This answer must be a valid email address.');
        }
    }

    private function validateSelectAnswer($validator, int $index, mixed $value, array $options): void
    {
        if (! is_string($value) || ! in_array($value, $options, true)) {
            $validator->errors()->add("answers.$index.value", 'This answer is not one of the allowed options.');
        }
    }

    private function validateMultiselectAnswer($validator, int $index, mixed $value, array $options): void
    {
        if (! is_array($value)) {
            $validator->errors()->add("answers.$index.value", 'This answer must be a list.');

            return;
        }

        foreach ($value as $option) {
            if (! is_string($option) || ! in_array($option, $options, true)) {
                $validator->errors()->add("answers.$index.value", 'This answer contains an option that is not allowed.');

                return;
            }
        }
    }

    private function validateBooleanAnswer($validator, int $index, mixed $value): void
    {
        if (! is_bool($value)) {
            $validator->errors()->add("answers.$index.value", 'This answer must be true or false.');
        }
    }

    private function isBlankDraftValue(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }
}
