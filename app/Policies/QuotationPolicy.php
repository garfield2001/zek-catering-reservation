<?php

namespace App\Policies;

use App\Models\Quotation;
use App\Models\QuotationAdjustmentRequest;
use App\Models\QuotationVersion;
use App\Models\User;
use App\Services\Access\PermissionResolver;

class QuotationPolicy
{
    public function __construct(private PermissionResolver $permissions) {}

    public function viewAny(User $user): bool
    {
        return $this->permissions->allows($user, 'quotation.view') || $this->permissions->allows($user, 'quotation.manage');
    }

    public function view(User $user, Quotation $quotation): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->permissions->allows($user, 'quotation.manage');
    }

    public function revise(User $user, Quotation $quotation): bool
    {
        return $this->permissions->allows($user, 'quotation.manage');
    }

    public function issue(User $user, QuotationVersion $version): bool
    {
        return $this->permissions->allows($user, 'quotation.issue');
    }

    public function requestAdjustment(User $user, QuotationVersion $version): bool
    {
        return $this->permissions->allows($user, 'quotation.adjustment.request');
    }

    public function applyDirectAdjustment(User $user, QuotationVersion $version): bool
    {
        return $user->active && $user->isAdmin();
    }

    public function reviewAdjustment(User $user, QuotationAdjustmentRequest $request): bool
    {
        return $this->permissions->allows($user, 'quotation.adjustment.approve');
    }

    public function withdraw(User $user, QuotationVersion $version): bool
    {
        return $this->permissions->allows($user, 'quotation.issue');
    }

    public function recordDelivery(User $user, QuotationVersion $version): bool
    {
        return $this->permissions->allows($user, 'quotation.delivery.record');
    }

    public function recordOfflineAcceptance(User $user, QuotationVersion $version): bool
    {
        return $this->permissions->allows($user, 'quotation.acceptance.record');
    }
}
