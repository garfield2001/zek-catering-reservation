<?php

namespace App\Services\BusinessPolicies;

use App\Models\BusinessPolicy;

class CurrentBusinessPolicy
{
    public function current(): BusinessPolicy
    {
        return BusinessPolicy::query()->where('key', 'current')->sole();
    }
}
