<?php

namespace App\Services\ReservationRequest;

use Illuminate\Support\Facades\Hash;

class CustomerAccessCode
{
    public function generate(): string
    {
        return strtoupper(bin2hex(random_bytes(8)));
    }

    public function hash(string $code): string
    {
        return Hash::make($code);
    }
}
