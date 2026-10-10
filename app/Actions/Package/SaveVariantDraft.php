<?php

namespace App\Actions\Package;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\VariantVersion;
use App\Services\Audit\AuditLogger;
use App\Services\Catalog\MinorMoney;
use App\Services\Catalog\PackageQuantityRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SaveVariantDraft
{
    public function __construct(private AuditLogger $audit, private MinorMoney $money, private PackageQuantityRules $quantities) {}

    public function handle(User $actor, Product $product, array $data, ?ProductVariant $variant = null, ?VariantVersion $version = null): VariantVersion
    {
        return DB::transaction(function () use ($actor, $product, $data, $variant, $version) {
            $product = Product::lockForUpdate()->findOrFail($product->id);
            $family = $product->family;
            if (! $product->active || ! $family->active || $family->code === 'food_tray') {
                throw ValidationException::withMessages(['product' => "{$family->name} is unavailable for new package drafts."]);
            }
            $variant = $variant ? ProductVariant::lockForUpdate()->findOrFail($variant->id) : new ProductVariant;
            abort_if($variant->exists && $variant->product_id !== $product->id, 404);
            Gate::forUser($actor->fresh())->authorize($variant->exists ? 'update' : 'create', $variant->exists ? $variant : ProductVariant::class);
            Gate::forUser($actor->fresh())->authorize('price', VariantVersion::class);
            $price = $this->money->parse($data['base_price_minor'], 'base_price_minor');
            if ($data['selection_policy'] === 'split' && $family->code !== 'food_pack') {
                throw ValidationException::withMessages(['selection_policy' => "Split allocations are not supported for {$family->name}; choose uniform selections."]);
            }
            $overrides = array_intersect_key($data, array_flip(['minimum_override', 'step_override', 'maximum_override', 'maximum_override_enabled']));
            $effective = $this->quantities->resolve($family, $overrides);
            $before = $version ? [...$version->only('base_price_minor', 'selection_policy', 'minimum_override', 'step_override',
                'maximum_override_enabled', 'maximum_override', 'draft_revision'),
                'variant' => $variant->only('code', 'name', 'description', 'sort_order', 'active')] : null;
            $variant->fill(['code' => $data['code'], 'name' => $data['name'],
                'description' => array_key_exists('description', $data) ? $data['description'] : $variant->description,
                'sort_order' => $data['sort_order'] ?? ($variant->sort_order ?? 0), 'active' => $data['active']]);
            if (! $variant->exists) {
                $variant->product_id = $product->id;
            }
            $variant->save();
            if ($version) {
                $version = VariantVersion::lockForUpdate()->findOrFail($version->id);
                abort_if($version->variant_id !== $variant->id || $version->status !== 'draft', 404);
                if ($version->draft_revision !== $data['draft_revision']) {
                    throw ValidationException::withMessages(['draft_revision' => 'This draft changed. Refresh before saving.']);
                }
                $version->draft_revision++;
            } else {
                $version = new VariantVersion;
                $version->variant_id = $variant->id;
                $version->family_id = $family->id;
                $version->version_number = 1;
                $version->status = 'draft';
                $version->draft_revision = 1;
                $version->created_by = $actor->id;
            }
            $version->fill([...$overrides, ...$effective, 'base_price_minor' => $price,
                'selection_policy' => $data['selection_policy'], 'price_basis' => match ($family->code) {
                    'main_catering' => 'per_pax', 'food_pack' => 'per_pack', default => 'per_set',
                }, 'currency' => 'PHP']);
            $version->save();
            $this->audit->record($before ? 'package.draft_updated' : 'package.draft_created', $actor, $version,
                $before, [...$version->only('base_price_minor', 'selection_policy', 'minimum_override', 'step_override',
                    'maximum_override_enabled', 'maximum_override', 'draft_revision'),
                    'variant' => $variant->only('code', 'name', 'description', 'sort_order', 'active')]);

            return $version;
        });
    }
}
