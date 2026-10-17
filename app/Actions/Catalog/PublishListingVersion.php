<?php

namespace App\Actions\Catalog;

use App\Models\IndividualListing;
use App\Models\ListingVersion;
use App\Models\ProductFamily;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Catalog\ListingConfiguration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PublishListingVersion
{
    public function __construct(private ListingConfiguration $configuration, private AuditLogger $audit) {}

    public function handle(User $actor, IndividualListing $listing, ListingVersion $version, int $revision): void
    {
        DB::transaction(function () use ($actor, $listing, $version, $revision) {
            Gate::forUser($actor->fresh())->authorize('publish', IndividualListing::class);
            ProductFamily::lockForUpdate()->findOrFail($listing->family_id);
            $listing = IndividualListing::lockForUpdate()->findOrFail($listing->id);
            $version = $listing->versions()->lockForUpdate()->findOrFail($version->id);
            if ($version->status === 'published' && $listing->current_version_id === $version->id) {
                return;
            }
            if ($version->status !== 'draft' || $version->draft_revision !== $revision) {
                throw ValidationException::withMessages(['version' => 'This Draft changed. Review the latest saved configuration before publishing.']);
            }
            if (! $listing->active || $listing->archived_at || ! $this->configuration->sourceAvailable($listing)) {
                throw ValidationException::withMessages(['listing' => 'Enable this item and its catalog sources before publication.']);
            }
            if ((int) $version->price_minor <= 0) {
                throw ValidationException::withMessages(['price_pesos' => 'Set a positive selling price before publication.']);
            }
            if ($version->has_selling_options && (! $version->sellingOptions()->exists()
                || (int) $version->sellingOptions()->min('price_minor') !== (int) $version->price_minor)) {
                throw ValidationException::withMessages(['selling_options' => 'Review and save all selling options before publication.']);
            }
            $version->fill(['status' => 'published', 'published_snapshot' => $this->configuration->snapshot($listing, $version), 'published_at' => now(), 'published_by' => $actor->id])->save();
            $listing->forceFill(['current_version_id' => $version->id])->save();
            $this->audit->record('listing.published', $actor, $version, null, ['version_number' => $version->version_number, 'price_minor' => $version->price_minor]);
        }, 3);
    }
}
