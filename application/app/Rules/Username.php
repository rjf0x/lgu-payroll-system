<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class Username implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/\A[a-z0-9][a-z0-9._-]{2,49}\z/', $value)) {
            $fail('The username must be 3–50 letters, numbers, dots, underscores or hyphens, starting with a letter or number.');
        }
    }
}
