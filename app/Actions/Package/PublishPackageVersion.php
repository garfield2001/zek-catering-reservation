<?php

namespace App\Actions\Package;

use App\Models\PackageVersion;
use App\Models\Product;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Package\ConfigurationFeasibility;
use App\Services\Package\PackageMenuConfiguration;
use App\Services\Package\PublishedSnapshot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PublishPackageVersion
{
    public function __construct(private ConfigurationFeasibility $feasibility, private PackageMenuConfiguration $main,
        private PublishedSnapshot $snapshots, private AuditLogger $audit) {}

    public function handle(User $actor, PackageVersion $attempt, int $revision): PackageVersion
    {
        return DB::transaction(function () use ($actor, $attempt, $revision): PackageVersion {
            if (DB::transactionLevel() === 1) {
                DB::statement('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
            }
            $product = Product::with('family')->lockForUpdate()->findOrFail($attempt->package_product_id);
            $version = PackageVersion::lockForUpdate()->where('package_product_id', $product->id)->findOrFail($attempt->id);
            Gate::forUser($actor->fresh())->authorize('publish', PackageVersion::class);
            if ($version->status === 'published' && $product->current_version_id === $version->id) {
                return $version;
            }
            if ($version->status !== 'draft' || $version->draft_revision !== $revision) {
                throw ValidationException::withMessages(['draft_revision' => 'This draft changed or is no longer editable. Reload before publishing.']);
            }
            if ($product->archived_at !== null || ! $product->active || $product->family->archived_at !== null || ! $product->family->active) {
                throw ValidationException::withMessages(['version' => 'The package or its collection is unavailable.']);
            }
            if ($version->currency !== 'PHP' || $version->price_basis !== $version->behavior()->priceBasis()
                || ($version->selection_policy === 'split' && ! $version->behavior()->supportsSplit())) {
                throw ValidationException::withMessages(['version' => 'The currency, pricing unit or selection arrangement conflicts with this collection.']);
            }
            if ($version->usesFoodMenu()) {
                $this->main->validateDraft($version);
            } else {
                $this->feasibility->find($version);
            }
            $snapshot = $this->snapshots->capture($product, $version);
            $previous = $product->currentVersion?->snapshot();
            $oldAdjustments = collect($previous['groups'] ?? [])->flatMap(fn ($group) => collect($group['options'])->mapWithKeys(fn ($option) => [$group['group_code'].':'.$option['offering_option_id'] => (string) $option['price_adjustment_minor']]))->filter(fn ($amount) => (int) $amount !== 0)->sortKeys()->all();
            $newAdjustments = collect($snapshot['groups'])->flatMap(fn ($group) => collect($group['options'])->mapWithKeys(fn ($option) => [$group['group_code'].':'.$option['offering_option_id'] => (string) $option['price_adjustment_minor']]))->filter(fn ($amount) => (int) $amount !== 0)->sortKeys()->all();
            if ((string) ($previous['version']['base_price_minor'] ?? '0') !== (string) $snapshot['version']['base_price_minor'] || $oldAdjustments !== $newAdjustments
                || collect($previous['menu']['food_items'] ?? [])->pluck('price_adjustment_minor', 'id')->filter(fn ($p) => (int) $p !== 0)->sortKeys()->all() !== collect($snapshot['menu']['food_items'] ?? [])->pluck('price_adjustment_minor', 'id')->filter(fn ($p) => (int) $p !== 0)->sortKeys()->all()) {
                Gate::forUser($actor->fresh())->authorize('price', PackageVersion::class);
            }
            foreach ($snapshot['groups'] as $group) {
                foreach ($group['options'] as $choice) {
                    DB::table('group_options')->where('id', $choice['group_option_id'])->update([
                        'snapshot_dish_id' => $choice['food_item']['id'], 'snapshot_category_id' => $choice['food_category']['id'],
                        'snapshot_offering_name' => $choice['offering']['name'], 'snapshot_dish_name' => $choice['food_item']['name'],
                        'snapshot_category_name' => $choice['food_category']['name'], 'snapshot_option_code' => $choice['serving_option']['option_code'],
                        'snapshot_option_label' => $choice['serving_option']['label'], 'snapshot_unit' => $choice['serving_option']['unit'],
                        'snapshot_quantity' => $choice['serving_option']['quantity'], 'snapshot_serving_pax' => $choice['serving_option']['serving_pax'],
                        'snapshot_serving_claim' => $choice['serving_option']['serving_claim'],
                    ]);
                }
            }
            $previousId = $product->current_version_id;
            $version->forceFill(['status' => 'published', 'published_snapshot' => $snapshot,
                'published_at' => now(), 'published_by' => $actor->id])->save();
            $product->forceFill(['current_version_id' => $version->id])->save();
            $this->audit->record('package.version_published', $actor, $version,
                ['previous_current_version_id' => $previousId], ['current_version_id' => $version->id, 'version_number' => $version->version_number]);

            return $version;
        }, 3);
    }
}
