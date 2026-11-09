<?php

namespace App\Policies;

use App\Models\QuotationTermsVersion;
use App\Models\User;

class QuotationTermsVersionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->active && $user->isAdmin();
    }

    public function view(User $user, QuotationTermsVersion $version): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, QuotationTermsVersion $version): bool
    {
        return $this->viewAny($user) && $version->status->value === 'draft';
    }

    public function approve(User $user, QuotationTermsVersion $version): bool
    {
        return $this->update($user, $version);
    }
}
