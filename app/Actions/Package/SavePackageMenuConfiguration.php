<?php

namespace App\Actions\Package;

use App\Models\PackageVersion;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Catalog\MinorMoney;
use App\Services\Package\PackageFoodSizes;
use App\Services\Package\PackageMenuConfiguration;
use App\Services\Package\PackageServingDefaults;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SavePackageMenuConfiguration
{
    public function __construct(private PackageMenuConfiguration $configuration, private AuditLogger $audit) {}

    public function handle(User $actor, PackageVersion $version, array $data): void
    {
        DB::transaction(function () use ($actor, $version, $data): void {
            $version = PackageVersion::lockForUpdate()->findOrFail($version->id);
            abort_if($version->status !== 'draft' || ! $version->family->behavior()->supportsPackageDrafts()
                || $version->product->archived_at !== null || $version->family->archived_at !== null, 404);
            Gate::forUser($actor->fresh())->authorize('update', $version);
            if ($version->draft_revision !== (int) $data['draft_revision']) {
                throw ValidationException::withMessages(['draft_revision' => 'This draft changed. Reload before saving.']);
            }
            $before = ['details' => $version->foodItems()->get()->mapWithKeys(fn ($dish) => [$dish->id => $dish->pivot->toArray()])->all(), 'food_items' => $version->foodItems()->pluck('dishes.id')->all(), 'required_categories' => $version->requiredCategories()->pluck('dish_categories.id')->all(), 'total_selection_count' => $version->total_selection_count];
            $details = $data['food_details'] ?? [];
            $sizes = app(PackageFoodSizes::class);
            $beforeSizes = $sizes->snapshot($version);
            $sizeInput = $data['food_sizes'] ?? $sizes->input($version);
            $normalizedSizes = $sizes->validateInput($version, $data['food_item_ids'], $sizeInput);
            foreach (array_keys($normalizedSizes) as $foodId) {
                $detail = $details[$foodId] ?? [];
                if (! empty($detail['included_quantity']) || ! empty($detail['included_unit'])
                    || app(MinorMoney::class)->fromPesos($detail['price_adjustment_pesos'] ?? '0', 'food_details') !== 0) {
                    throw ValidationException::withMessages(['food_sizes' => 'Use the size configuration for this food instead of a separate included amount or general extra price. This prevents duplicate measurements and double charging.']);
                }
            }
            $servings = app(PackageServingDefaults::class);
            $before['serving_default'] = $servings->snapshot($version);
            $defaultInput = array_key_exists('serving_default', $data) ? $data['serving_default'] : $servings->input($version);
            $version->forceFill($servings->normalize($version, $defaultInput))->save();
            $normalizedExistingSizes = $sizes->configuration($version);
            if ($normalizedSizes != $normalizedExistingSizes) {
                Gate::forUser($actor->fresh())->authorize('price', PackageVersion::class);
            }
            $before['food_sizes'] = $beforeSizes;
            $oldPrices = $version->foodItems()->get()->mapWithKeys(fn ($dish) => [$dish->id => (int) $dish->pivot->getAttribute('price_adjustment_minor')])->filter()->sortKeys()->all();
            $newPrices = collect($details)->map(fn ($item) => app(MinorMoney::class)->fromPesos($item['price_adjustment_pesos'] ?? '0', 'food_details'))->filter()->sortKeys()->all();
            if ($oldPrices !== $newPrices) {
                Gate::forUser($actor->fresh())->authorize('price', PackageVersion::class);
            }
            if ($version->groups()->exists()) {
                throw ValidationException::withMessages(['food_items' => 'This draft uses advanced serving choices. Create a new package with the food builder; do not silently replace its configuration.']);
            }
            $version->forceFill(['total_selection_count' => $version->family->behavior_type === 'fixed_bundle' ? count($data['food_item_ids']) : (int) $data['total_selection_count']])->save();
            $sizes->clear($version);
            $this->configuration->replace($version, $data['food_item_ids'], $data['required_category_ids'], $details);
            $sizes->replace($version, $normalizedSizes);
            $this->configuration->validateDraft($version->fresh());
            $version->increment('draft_revision');
            $this->audit->record('package.menu_updated', $actor, $version, $before, ['details' => $details, 'serving_default' => $servings->snapshot($version), 'food_sizes' => $sizes->snapshot($version), 'food_items' => $data['food_item_ids'], 'required_categories' => $data['required_category_ids'], 'total_selection_count' => $version->total_selection_count]);
        });
    }
}
