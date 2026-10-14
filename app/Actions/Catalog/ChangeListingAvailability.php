<?php

namespace App\Actions\Catalog;

use App\Models\IndividualListing;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ChangeListingAvailability
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(User $actor, IndividualListing $listing, string $operation): void
    {
        DB::transaction(function () use ($actor, $listing, $operation) {
            Gate::forUser($actor->fresh())->authorize('lifecycle', IndividualListing::class);
            $listing = IndividualListing::lockForUpdate()->findOrFail($listing->id);
            abort_if($listing->archived_at || ! in_array($operation, ['enable', 'disable', 'archive'], true), 404);
            $before = $listing->only('active', 'archived_at');
            if ($operation === 'archive') {
                foreach ($listing->versions()->where('status', 'draft')->lockForUpdate()->get() as $draft) {
                    $draft->forceFill(['status' => 'abandoned'])->save();
                    $this->audit->record('listing.draft_abandoned', $actor, $draft);
                }
            }
            $listing->forceFill(['active' => $operation === 'enable', 'archived_at' => $operation === 'archive' ? now() : null])->save();
            $this->audit->record('listing.'.$operation, $actor, $listing, $before, $listing->only('active', 'archived_at'));
        });
    }
}
