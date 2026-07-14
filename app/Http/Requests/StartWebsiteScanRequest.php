<?php

namespace App\Http\Requests;

use App\Rules\SafePublicHttpUrlRule;
use App\Support\SafePublicHttpUrl;
use Illuminate\Foundation\Http\FormRequest;

class StartWebsiteScanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'url' => ['required', 'string', 'max:2048', 'url:http,https', new SafePublicHttpUrlRule],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('url'))) {
            $this->merge([
                'url' => SafePublicHttpUrl::normalize($this->input('url')),
            ]);
        }
    }
}
