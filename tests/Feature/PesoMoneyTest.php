<?php

namespace Tests\Feature;

use App\Services\Catalog\MinorMoney;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PesoMoneyTest extends TestCase
{
    public function test_peso_strings_convert_to_exact_integer_centavos(): void
    {
        $money = new MinorMoney;
        $this->assertSame(0, $money->fromPesos('0'));
        $this->assertSame(5, $money->fromPesos('0.05'));
        $this->assertSame(25050, $money->fromPesos('250.50'));
        $this->assertSame(9223372036854775807, $money->fromPesos('92233720368547758.07'));
    }

    public function test_invalid_or_excessive_peso_values_are_rejected(): void
    {
        foreach (['-1', '1.234', '1e3', '01.00', '92233720368547758.08', 1.5] as $value) {
            try {
                (new MinorMoney)->fromPesos($value);
                $this->fail('Invalid peso value was accepted.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('amount', $exception->errors());
            }
        }
    }
}
