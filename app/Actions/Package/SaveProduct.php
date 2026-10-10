<?php

namespace App\Actions\Package;

use App\Models\Product;
use App\Models\ProductFamily;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SaveProduct
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(User $actor, array $data, ?Product $product = null): Product
    {
        return DB::transaction(function () use ($actor, $data, $product) {
            $product = $product ? Product::lockForUpdate()->findOrFail($product->id) : new Product;
            Gate::forUser($actor->fresh())->authorize($product->exists ? 'update' : 'create', $product->exists ? $product : Product::class);
            $family = ProductFamily::findOrFail($data['family_id']);
            if (! $family->active) {
                throw ValidationException::withMessages(['family_id' => "{$family->name} is disabled for new package drafts."]);
            }
            if ($family->code === 'food_tray') {
                throw ValidationException::withMessages(['family_id' => "{$family->name} uses individual direct listings, not package variants."]);
            }
            $before = $product->exists ? $product->only('family_id', 'code', 'name', 'description', 'sort_order', 'active') : null;
            $product->fill($data)->save();
            $this->audit->record($before ? 'package.product_updated' : 'package.product_created', $actor, $product,
                $before, $product->only('family_id', 'code', 'name', 'description', 'sort_order', 'active'));

            return $product;
        });
    }
}
