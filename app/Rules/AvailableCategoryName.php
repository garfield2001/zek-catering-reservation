<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

class AvailableCategoryName implements ValidationRule
{
    public function __construct(private ?int $exceptId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && DB::table('dish_categories')
            ->whereRaw('name_key = lower(btrim(?))', [$value])
            ->when($this->exceptId, fn ($query) => $query->where('id', '!=', $this->exceptId))->exists()) {
            $fail('This category name is already in use.');
        }
    }
}
