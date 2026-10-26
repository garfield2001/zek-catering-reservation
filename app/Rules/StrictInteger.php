<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Public JSON endpoints never coerce a numeric-looking malformed value into a quantity or identifier. */
class StrictInteger implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_int($value)) {
            $fail('The :attribute must be a whole number.');
        }
    }
}
