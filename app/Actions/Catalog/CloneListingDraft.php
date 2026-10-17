<?php

namespace App\Actions\Catalog;

use App\Models\IndividualListing;
use App\Models\ListingVersion;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CloneListingDraft
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(User $actor, IndividualListing $listing, ListingVersion $source): ListingVersion
    {
        return DB::transaction(function () use ($actor, $listing, $source) {
            Gate::forUser($actor->fresh())->authorize('update', $listing);
            Gate::forUser($actor)->authorize('history', IndividualListing::class);
            $listing = IndividualListing::lockForUpdate()->findOrFail($listing->id);
            abort_if($listing->archived_at || $listing->family->archived_at, 404);
            $source = $listing->versions()->where('status', 'published')->findOrFail($source->id);
            if ($listing->versions()->where('status', 'draft')->exists()) {
                throw ValidationException::withMessages(['version' => 'An open Draft already exists. Edit it before creating another revision.']);
            }
            $draft = $source->replicate(['published_snapshot', 'published_at', 'published_by']);
            $draft->fill(['version_number' => $listing->versions()->max('version_number') + 1, 'draft_revision' => 1, 'status' => 'draft', 'cloned_from_id' => $source->id])->save();
            foreach ($source->sellingOptions as $option) {
                $draft->sellingOptions()->create($option->only(['label', 'price_minor', 'sort_order']));
            }
            $this->audit->record('listing.draft_cloned', $actor, $draft, null, ['source_version_id' => $source->id]);

            return $draft;
        });
    }
}
