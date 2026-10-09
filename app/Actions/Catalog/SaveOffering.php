<?php

namespace App\Actions\Catalog;

use App\Models\Dish;
use App\Models\DishOffering;
use App\Models\ProductFamily;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Catalog\CatalogEligibility;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SaveOffering
{
    public function __construct(private AuditLogger $audit, private CatalogEligibility $eligibility) {}

    public function handle(User $actor, Dish $dish, array $data, ?DishOffering $offering = null): DishOffering
    {
        return DB::transaction(function () use ($actor, $dish, $data, $offering) {
            $dish = Dish::lockForUpdate()->findOrFail($dish->id);
            $offering = $offering ? DishOffering::lockForUpdate()->findOrFail($offering->id) : new DishOffering;
            Gate::forUser($actor->fresh())->authorize($offering->exists ? 'update' : 'create',
                $offering->exists ? $offering : DishOffering::class);
            abort_if($offering->exists && $offering->dish_id !== $dish->id, 404);
            $family = ProductFamily::lockForUpdate()->findOrFail($offering->exists ? $offering->family_id : $data['family_id']);
            if (! $offering->exists || $data['active']) {
                $this->eligibility->ensureOfferingCanActivate($dish, $family);
            }
            $before = $offering->exists ? $offering->only('dish_id', 'family_id', 'code', 'name', 'description', 'active') : null;
            $offering->fill($data);
            if (! $offering->exists) {
                $offering->dish_id = $dish->id;
            }
            $offering->save();
            $this->audit->record($before ? 'catalog.offering_updated' : 'catalog.offering_created', $actor,
                $offering, $before, $offering->only('dish_id', 'family_id', 'code', 'name', 'description', 'active'));

            return $offering;
        });
    }
}
