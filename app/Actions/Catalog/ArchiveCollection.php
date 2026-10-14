<?php

namespace App\Actions\Catalog;

use App\Models\IndividualListing;
use App\Models\PackageVersion;
use App\Models\Product;
use App\Models\ProductFamily;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ArchiveCollection
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(User $actor, ProductFamily $family): void
    {
        DB::transaction(function () use ($actor, $family): void {
            $family = ProductFamily::lockForUpdate()->findOrFail($family->id);
            Gate::forUser($actor->fresh())->authorize('manage', ProductFamily::class);
            abort_if($family->archived_at !== null, 404);
            $drafts = PackageVersion::query()->where('family_id', $family->id)->where('status', 'draft')
                ->orderBy('id')->lockForUpdate()->get();
            if ($family->products()->exists()) {
                Gate::forUser($actor->fresh())->authorize('lifecycle', Product::class);
            }
            if ($family->listings()->exists()) {
                Gate::forUser($actor->fresh())->authorize('lifecycle', IndividualListing::class);
                foreach ($family->listings()->orderBy('id')->lockForUpdate()->get() as $listing) {
                    foreach ($listing->versions()->where('status', 'draft')->lockForUpdate()->get() as $draft) {
                        $draft->forceFill(['status' => 'abandoned'])->save();
                        $this->audit->record('listing.draft_abandoned', $actor, $draft, null, ['archived_collection_id' => $family->id]);
                    }
                }
            }
            $before = $family->only('name', 'active', 'archived_at');
            $family->forceFill(['active' => false, 'archived_at' => now()])->save();
            foreach ($drafts as $draft) {
                $draft->forceFill(['status' => 'abandoned', 'abandoned_at' => now(), 'abandoned_by' => $actor->id])->save();
                $this->audit->record('package.draft_abandoned', $actor, $draft, null,
                    ['archived_collection_id' => $family->id]);
            }
            $this->audit->record('catalog.collection_archived', $actor, $family, $before,
                $family->only('name', 'active', 'archived_at'));
        });
    }
}
