<?php

namespace App\Actions\Package;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\VariantVersion;
use App\Services\Audit\AuditLogger;
use App\Services\Package\ConfigurationFeasibility;
use App\Services\Package\PublishedSnapshot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PublishVariantVersion
{
    public function __construct(private ConfigurationFeasibility $feasibility,
        private PublishedSnapshot $snapshots, private AuditLogger $audit) {}

    public function handle(User $actor, VariantVersion $attempt, int $revision): VariantVersion
    {
        return DB::transaction(function () use ($actor, $attempt, $revision) {
            if (DB::transactionLevel() === 1) {
                DB::statement('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
            }
            $owner = ProductVariant::findOrFail($attempt->variant_id);
            $product = Product::with('family')->lockForUpdate()->findOrFail($owner->product_id);
            $variant = ProductVariant::lockForUpdate()->findOrFail($owner->id);
            Gate::forUser($actor->fresh())->authorize('publish', VariantVersion::class);
            $version = VariantVersion::lockForUpdate()->findOrFail($attempt->id);
            if ($version->status === 'published' && $variant->current_version_id === $version->id) {
                return $version; // A retry of an already committed publication.
            }
            if ($version->status !== 'draft' || $version->variant_id !== $variant->id) {
                throw ValidationException::withMessages(['version' => 'Only the active draft can be published.']);
            }
            if ($version->draft_revision !== $revision) {
                throw ValidationException::withMessages(['draft_revision' => 'This draft changed. Reload the review before publishing.']);
            }
            if ($product->archived_at !== null || $variant->archived_at !== null
                || $product->family->archived_at !== null || ! $product->family->active) {
                throw ValidationException::withMessages(['version' => 'The package or its collection is archived or unavailable.']);
            }
            if ($version->currency !== 'PHP' || $version->price_basis !== $product->family->behavior()->priceBasis()
                || ($version->selection_policy === 'split' && ! $product->family->behavior()->supportsSplit())) {
                throw ValidationException::withMessages(['version' => 'The currency, pricing unit or selection arrangement conflicts with this collection.']);
            }
            $this->feasibility->find($version);
            $snapshot = $this->snapshots->capture($product, $variant, $version);
            $current = $variant->currentVersion;
            if ($this->pricesChanged($current?->snapshot(), $snapshot)) {
                Gate::forUser($actor->fresh())->authorize('price', VariantVersion::class);
            }
            $previousId = $variant->current_version_id;
            $version->forceFill(['status' => 'published', 'published_snapshot' => $snapshot,
                'published_at' => now(), 'published_by' => $actor->id,
                'minimum_quantity' => $snapshot['version']['minimum_quantity'],
                'quantity_step' => $snapshot['version']['quantity_step'],
                'maximum_quantity' => $snapshot['version']['maximum_quantity']])->save();
            $variant->forceFill(['current_version_id' => $version->id])->save();
            $this->audit->record('package.version_published', $actor, $version,
                ['previous_current_version_id' => $previousId],
                ['current_version_id' => $version->id, 'version_number' => $version->version_number,
                    'draft_revision' => $version->draft_revision]);

            return $version;
        }, 3);
    }

    private function pricesChanged(?array $previous, array $next): bool
    {
        if ((string) ($previous['version']['base_price_minor'] ?? '0') !== (string) $next['version']['base_price_minor']) {
            return true;
        }
        $adjustments = function (?array $snapshot): array {
            $prices = [];
            foreach ($snapshot['groups'] ?? [] as $group) {
                foreach ($group['options'] ?? [] as $option) {
                    if ((int) $option['price_adjustment_minor'] > 0) {
                        $prices[$group['group_code'].'/'.$option['offering_option_id']] = (string) $option['price_adjustment_minor'];
                    }
                }
            }
            ksort($prices);

            return $prices;
        };

        return $adjustments($previous) !== $adjustments($next);
    }
}
