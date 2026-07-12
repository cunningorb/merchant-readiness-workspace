<?php

namespace App\Http\Requests;

use App\Services\Imports\Demo\DemoScenarios;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'provider' => ['required', 'string', Rule::in(['csv', 'demo'])],
            'method' => ['required', 'string', Rule::in(['csv', 'demo'])],
            'scenario' => [
                Rule::requiredIf(fn () => $this->input('provider') === 'demo'),
                'nullable',
                'string',
                Rule::in(DemoScenarios::SCENARIOS),
            ],
        ];
    }
}
