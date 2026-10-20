<?php

namespace App\Actions\Package;

use App\Models\PackageVersion;
use App\Models\Product;
use App\Models\ProductFamily;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Catalog\PackageQuantityRules;
use App\Services\Catalog\ServicePackageConfiguration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SaveProduct
{
    public function __construct(private AuditLogger $audit, private PackageQuantityRules $quantities) {}

    public function handle(User $actor, array $data, ?Product $product = null): Product
    {
        return DB::transaction(function () use ($actor, $data, $product) {
            $product = $product ? Product::lockForUpdate()->findOrFail($product->id) : new Product;
            abort_if($product->exists && $product->archived_at !== null, 404);
            Gate::forUser($actor->fresh())->authorize($product->exists ? 'update' : 'create', $product->exists ? $product : Product::class);
            if ($product->exists && (int) $data['family_id'] !== $product->family_id) {
                throw ValidationException::withMessages(['family_id' => 'A package product cannot be moved to another Collection.']);
            }
            if ($product->exists && (bool) $data['active'] !== $product->active) {
                Gate::forUser($actor->fresh())->authorize('lifecycle', $product);
            }
            $family = ProductFamily::lockForUpdate()->findOrFail($data['family_id']);
            if (! $product->exists && isset($data['creation_token'])) {
                $existing = Product::where('creation_token', $data['creation_token'])->first();
                if ($existing) {
                    if ($existing->family_id !== $family->id || $existing->name !== $data['name']) {
                        throw ValidationException::withMessages(['creation_token' => 'This creation request was already used for a different package.']);
                    }

                    return $existing;
                }
            }
            if ((! $product->exists && ! $family->active) || $family->archived_at !== null) {
                throw ValidationException::withMessages(['family_id' => "{$family->name} is disabled for new package drafts."]);
            }
            if (! $family->behavior()->supportsPackageDrafts()) {
                throw ValidationException::withMessages(['family_id' => "{$family->name} uses individual direct listings, not package products."]);
            }
            $before = $product->exists ? $product->only('family_id', 'code', 'name', 'description', 'sort_order', 'active') : null;
            $product->fill(collect($data)->except(['code', 'sort_order', 'creation_token'])->all());
            if (! $product->exists) {
                if ($family->package_format !== null) {
                    $configuration = app(ServicePackageConfiguration::class);
                    $configuration->normalize($family, $configuration->input($family));
                    $product->forceFill(['service_format_owned' => true]);
                }
                $product->code = $data['code'] ?? 'product_'.strtolower((string) Str::ulid());
                $product->sort_order = (int) Product::where('family_id', $family->id)->max('sort_order') + 1;
                $product->creation_token = $data['creation_token'] ?? null;
            }
            $product->save();
            if ($before === null) {
                $effective = $this->quantities->resolve($family, []);
                PackageVersion::create(['package_product_id' => $product->id, 'family_id' => $family->id,
                    'version_number' => 1, 'status' => 'draft', 'draft_revision' => 1,
                    'base_price_minor' => 0, 'currency' => 'PHP', 'price_basis' => $family->behavior()->priceBasis(),
                    'selection_policy' => 'uniform', 'total_selection_count' => $data['total_selection_count'] ?? null,
                    ...$effective, 'created_by' => $actor->id,
                    ...($family->package_format === null ? [] : ['package_format' => $family->package_format,
                        'presentation_quantity' => $family->default_presentation_quantity, 'presentation_unit_id' => $family->default_presentation_unit_id])]);
            }
            $this->audit->record($before ? 'package.product_updated' : 'package.product_created', $actor, $product,
                $before, $product->only('family_id', 'code', 'name', 'description', 'sort_order', 'active'));

            return $product;
        });
    }
}
