<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SupportedPassword implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return; // The string rule reports malformed input.
        }

        if (strlen($value) > 72) {
            $fail('The :attribute must not exceed 72 bytes. Some characters use more than one byte.');
        }

        if (str_contains($value, "\0")) {
            $fail('The :attribute must not contain null characters.');
        }
    }
}
