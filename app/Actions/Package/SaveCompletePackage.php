<?php

namespace App\Actions\Package;

use App\Models\PackageVersion;
use App\Models\Product;
use App\Models\User;
use App\Services\Catalog\MinorMoney;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** A single commercial save; nested domain actions share this transaction. */
class SaveCompletePackage
{
    public function __construct(private SaveProduct $products, private SavePackageMenuConfiguration $menus,
        private SavePackageVersion $pricing, private ClonePackageDraft $clones, private MinorMoney $money) {}

    public function handle(User $actor, array $data, ?Product $product = null): Product
    {
        return DB::transaction(function () use ($actor, $data, $product): Product {
            Gate::forUser($actor->fresh())->authorize($product ? 'update' : 'create', $product ?? Product::class);
            $creating = $product === null;
            $hash = hash('sha256', json_encode(Arr::sortRecursive(['actor_id' => $actor->id, ...$data]), JSON_THROW_ON_ERROR));
            if ($creating) {
                DB::select('select pg_advisory_xact_lock(hashtextextended(?, 0))', ['package-create:'.$data['creation_token']]);
                $existing = Product::where('creation_token', $data['creation_token'])->first();
                if ($existing) {
                    if (! hash_equals((string) $existing->getAttribute('creation_hash'), $hash)) {
                        throw ValidationException::withMessages(['creation_token' => 'This save request was already used with different package details. Reload before creating another package.']);
                    }

                    return $existing;
                }
                Gate::forUser($actor)->authorize('price', PackageVersion::class);
            } else {
                $product = Product::lockForUpdate()->findOrFail($product->id);
                abort_if($product->family_id !== (int) $data['family_id'], 404);
            }

            $details = Arr::only($data, ['family_id', 'name', 'description', 'active', 'creation_token']);
            if (! $creating) {
                $version = $product->versions()->lockForUpdate()->findOrFail($data['version_id']);
                if ($version->draft_revision !== (int) $data['draft_revision']) {
                    throw ValidationException::withMessages(['draft_revision' => 'This package changed. Reload and review the latest saved configuration.']);
                }
                if ($version->status === 'published') {
                    $version = $this->clones->handle($actor, $product, $version);
                }
                abort_unless($version->status === 'draft', 404);
            }
            $product = $this->products->handle($actor, $details, $product);
            if ($creating) {
                $product->forceFill(['creation_hash' => $hash])->save();
                $version = $product->versions()->where('status', 'draft')->sole();
            }

            $minor = isset($data['base_price_pesos']) ? $this->money->fromPesos($data['base_price_pesos'], 'base_price_pesos') : $this->money->parse($data['base_price_minor'], 'base_price_minor');
            $priceChanged = $creating || (string) $minor !== (string) $version->base_price_minor;
            foreach (['selection_policy', 'minimum_quantity', 'quantity_step', 'maximum_quantity', 'estimated_capacity', 'price_basis'] as $field) {
                $incoming = array_key_exists($field, $data) ? $data[$field] : $version->getAttribute($field);
                $priceChanged = $priceChanged || (string) $incoming !== (string) $version->getAttribute($field);
            }
            if ($priceChanged) {
                $version = $this->pricing->handle($actor, $product, [...$data, 'draft_revision' => $version->draft_revision], $version);
            }
            $this->menus->handle($actor, $version, [...$data, 'draft_revision' => $version->draft_revision]);

            return $product->fresh();
        }, 3);
    }
}
