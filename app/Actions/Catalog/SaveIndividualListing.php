<?php

namespace App\Actions\Catalog;

use App\Models\IndividualListing;
use App\Models\ListingVersion;
use App\Models\ProductFamily;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Catalog\ListingConfiguration;
use App\Services\Catalog\ListingSellingOptions;
use App\Services\Catalog\MinorMoney;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SaveIndividualListing
{
    public function __construct(private MinorMoney $money, private ListingConfiguration $configuration, private AuditLogger $audit) {}

    public function handle(User $actor, array $data, ?IndividualListing $listing = null): IndividualListing
    {
        return DB::transaction(function () use ($actor, $data, $listing) {
            Gate::forUser($actor->fresh())->authorize($listing ? 'update' : 'create', $listing ?? IndividualListing::class);
            $data['selling_unit'] = $this->configuration->normalizeSellingUnit($data['selling_unit'] ?? null);
            $family = ProductFamily::lockForUpdate()->findOrFail($data['family_id']);
            abort_unless($family->behavior_type === 'direct_sale' && ! $family->archived_at, 404);
            $creating = ! $listing;
            $hash = hash('sha256', json_encode(Arr::sortRecursive(['actor_id' => $actor->id, ...$data]), JSON_THROW_ON_ERROR));
            if ($creating) {
                Gate::forUser($actor)->authorize('price', IndividualListing::class);
                DB::select('select pg_advisory_xact_lock(hashtextextended(?, 0))', ['listing-create:'.$data['creation_token']]);
                $existing = IndividualListing::where('creation_token', $data['creation_token'])->first();
                if ($existing) {
                    if (! hash_equals($existing->creation_hash, $hash)) {
                        throw ValidationException::withMessages(['creation_token' => 'This save request has already been used. Reload before creating another item.']);
                    }

                    return $existing;
                }
                if (($data['offering_option_id'] ?? null) === null) {
                    DB::select('select pg_advisory_xact_lock(hashtextextended(?, 0))', [
                        'listing-source:'.$family->id.':'.$data['dish_id'],
                    ]);
                    if (IndividualListing::where('family_id', $family->id)
                        ->where('dish_id', $data['dish_id'])
                        ->whereNull('offering_option_id')
                        ->exists()) {
                        throw ValidationException::withMessages([
                            'dish_id' => 'This Food Item is already configured for this service. Edit its existing listing instead.',
                        ]);
                    }
                }
                $listing = new IndividualListing([...Arr::only($data, ['family_id', 'dish_id', 'offering_option_id', 'creation_token']), 'creation_hash' => $hash, 'code' => 'listing_'.strtolower((string) Str::ulid()), 'active' => true]);
            } else {
                $listing = IndividualListing::lockForUpdate()->findOrFail($listing->id);
                abort_if($listing->archived_at !== null, 404);
                foreach (['family_id', 'dish_id', 'offering_option_id'] as $field) {
                    if ((string) ($data[$field] ?? null) !== (string) $listing->getAttribute($field)) {
                        throw ValidationException::withMessages([$field => 'The source of a saved listing cannot change. Create a separate listing for another Food Item.']);
                    }
                }
            }
            if (! $this->configuration->sourceAvailable($listing)) {
                throw ValidationException::withMessages(['dish_id' => 'Choose an active Food Item and a matching active Serving Option, if used.']);
            }
            $optionService = app(ListingSellingOptions::class);
            $options = array_key_exists('selling_options', $data) ? $optionService->normalize($data['selling_options']) : null;
            $price = $options !== null ? (string) min(array_column($options, 'price_minor')) : $this->money->fromPesos($data['price_pesos'], 'price_pesos');
            if (($data['maximum_quantity'] ?? null) !== null && (int) $data['maximum_quantity'] < (int) $data['minimum_quantity']) {
                throw ValidationException::withMessages(['maximum_quantity' => 'Maximum purchase must be at least the minimum.']);
            }
            $usesFoodName = ! isset($data['name']) || trim($data['name']) === '';
            $terms = [...Arr::only($data, ['description', 'selling_unit', 'minimum_quantity', 'quantity_step', 'maximum_quantity']),
                'name' => $usesFoodName ? $listing->dish->name : trim($data['name']), 'uses_food_name' => $usesFoodName, 'price_minor' => $price];
            if ($options !== null) {
                $terms['has_selling_options'] = true;
            }
            $before = null;
            if ($creating) {
                $listing->save();
                $version = new ListingVersion(['listing_id' => $listing->id, 'version_number' => 1, 'draft_revision' => 1, 'status' => 'draft', 'currency' => 'PHP']);
            } else {
                $version = $listing->versions()->lockForUpdate()->findOrFail($data['version_id']);
                if ($version->draft_revision !== (int) $data['draft_revision']) {
                    throw ValidationException::withMessages(['draft_revision' => 'This item changed. Reload the saved configuration before editing.']);
                }
                $before = $version->only(array_keys($terms));
                if ($version->has_selling_options && $options === null) {
                    throw ValidationException::withMessages(['selling_options' => 'Keep at least one selling option. Reload the current item editor before saving.']);
                }
                $before['selling_options'] = $optionService->facts($version);
                if ($options !== null && $options !== $before['selling_options']) {
                    Gate::forUser($actor)->authorize('price', IndividualListing::class);
                }
                foreach (['price_minor', 'selling_unit', 'minimum_quantity', 'quantity_step', 'maximum_quantity'] as $field) {
                    if ((string) ($terms[$field] ?? null) !== (string) $version->getAttribute($field)) {
                        Gate::forUser($actor)->authorize('price', IndividualListing::class);
                    }
                }
                if ($version->status === 'published') {
                    $version = app(CloneListingDraft::class)->handle($actor, $listing, $version);
                }
                abort_unless($version->status === 'draft', 404);
                $version->draft_revision++;
            }
            $version->fill($terms)->save();
            if ($options !== null) {
                $version->sellingOptions()->delete();
                $version->sellingOptions()->createMany($options);
            }
            $this->audit->record($creating ? 'listing.created' : 'listing.draft_updated', $actor, $version, $before, [...$version->only(array_keys($terms)), 'selling_options' => $optionService->facts($version)]);

            return $listing->fresh();
        }, 3);
    }
}
