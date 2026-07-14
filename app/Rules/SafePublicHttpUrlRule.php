<?php

namespace App\Rules;

use App\Support\SafePublicHttpUrl;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SafePublicHttpUrlRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! SafePublicHttpUrl::isAllowed($value)) {
            $fail('Enter a public http(s) website URL.');
        }
    }
}
