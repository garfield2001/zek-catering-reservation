<?php

namespace App\Services\Catalog;

use Illuminate\Validation\ValidationException;

class MinorMoney
{
    public function fromPesos(mixed $value, string $field = 'amount'): int
    {
        if (! is_string($value) || ! preg_match('/^(0|[1-9][0-9]*)(?:\.([0-9]{1,2}))?$/D', $value, $matches)) {
            throw ValidationException::withMessages([$field => 'Enter a nonnegative peso amount with at most two decimal places.']);
        }
        $centavos = ltrim($matches[1].str_pad($matches[2] ?? '', 2, '0'), '0');

        return $this->parse($centavos === '' ? '0' : $centavos, $field);
    }

    public function parse(mixed $value, string $field = 'amount'): int
    {
        if (! is_int($value) && ! is_string($value)) {
            throw ValidationException::withMessages([$field => 'Use a nonnegative integer centavo amount.']);
        }
        $text = (string) $value;
        if (! preg_match('/^(0|[1-9][0-9]*)$/D', $text)
            || strlen($text) > strlen((string) PHP_INT_MAX)
            || (strlen($text) === strlen((string) PHP_INT_MAX) && strcmp($text, (string) PHP_INT_MAX) > 0)) {
            throw ValidationException::withMessages([$field => 'The centavo amount is invalid or exceeds the supported range.']);
        }

        return (int) $text;
    }

    public function add(int $left, int $right): int
    {
        if ($left < 0 || $right < 0 || $right > PHP_INT_MAX - $left) {
            throw ValidationException::withMessages(['price' => 'The calculated amount exceeds the supported range.']);
        }

        return $left + $right;
    }

    public function multiply(int $amount, int $quantity): int
    {
        if ($amount < 0 || $quantity < 0 || ($quantity !== 0 && $amount > intdiv(PHP_INT_MAX, $quantity))) {
            throw ValidationException::withMessages(['price' => 'The calculated amount exceeds the supported range.']);
        }

        return $amount * $quantity;
    }
}
