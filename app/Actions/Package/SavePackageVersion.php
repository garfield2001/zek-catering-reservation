<?php

namespace App\Actions\Package;

use App\Models\PackageVersion;
use App\Models\Product;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Catalog\MinorMoney;
use App\Services\Catalog\PackageQuantityRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SavePackageVersion
{
    public function __construct(private AuditLogger $audit, private MinorMoney $money, private PackageQuantityRules $quantities) {}

    public function handle(User $actor, Product $product, array $data, PackageVersion $version): PackageVersion
    {
        return DB::transaction(function () use ($actor, $product, $data, $version) {
            $product = Product::lockForUpdate()->findOrFail($product->id);
            $family = $product->family;
            abort_if($product->archived_at !== null || $family->archived_at !== null
                || ! $family->behavior()->supportsPackageDrafts(), 404);
            $version = PackageVersion::lockForUpdate()->findOrFail($version->id);
            abort_if($version->package_product_id !== $product->id || $version->status !== 'draft', 404);
            Gate::forUser($actor->fresh())->authorize('update', $version);
            Gate::forUser($actor->fresh())->authorize('price', PackageVersion::class);

            $price = array_key_exists('base_price_pesos', $data)
                ? $this->money->fromPesos($data['base_price_pesos'], 'base_price_pesos')
                : $this->money->parse($data['base_price_minor'], 'base_price_minor');
            $basis = $data['price_basis'] ?? $version->price_basis;
            if ($version->package_format !== null && $data['selection_policy'] !== 'uniform') {
                throw ValidationException::withMessages(['selection_policy' => 'This Package Format repeats one complete food combination across purchased units. Different meal groups are not supported.']);
            }
            if ($family->behavior_type !== 'package' && $basis !== $family->behavior()->priceBasis()
                || $family->behavior_type === 'package' && ! in_array($basis, ['per_set', 'per_pack'], true)) {
                throw ValidationException::withMessages(['price_basis' => 'Choose a supported selling unit for this package.']);
            }
            $before = $version->only('base_price_minor', 'price_basis', 'selection_policy', 'minimum_quantity', 'quantity_step', 'maximum_quantity', 'estimated_capacity', 'total_selection_count', 'draft_revision');
            $version->price_basis = $basis;
            if ($data['selection_policy'] === 'split' && ! $version->behavior()->supportsSplit()) {
                throw ValidationException::withMessages(['selection_policy' => "Split allocations are not supported for {$family->name}; choose uniform selections."]);
            }
            $overrides = array_intersect_key($data, array_flip(['minimum_quantity', 'quantity_step', 'maximum_quantity']));
            $effective = $this->quantities->resolve($family, $overrides);
            if ($version->draft_revision !== (int) $data['draft_revision']) {
                throw ValidationException::withMessages(['draft_revision' => 'This draft changed. Refresh before saving.']);
            }
            $version->increment('draft_revision');
            $version->fill([...$overrides, ...$effective, 'base_price_minor' => $price,
                'selection_policy' => $data['selection_policy'], 'estimated_capacity' => $family->behavior_type === 'main_package' ? null : ($data['estimated_capacity'] ?? null),
                'price_basis' => $basis, 'currency' => 'PHP']);
            $version->save();
            $this->audit->record('package.draft_updated', $actor, $version, $before,
                $version->only('base_price_minor', 'price_basis', 'selection_policy', 'minimum_quantity', 'quantity_step', 'maximum_quantity', 'estimated_capacity', 'total_selection_count', 'draft_revision'));

            return $version;
        });
    }
}
