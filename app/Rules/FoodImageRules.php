<?php

namespace App\Rules;

class FoodImageRules
{
    public static function upload(): array
    {
        return ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=3000,max_height=3000'];
    }
}
