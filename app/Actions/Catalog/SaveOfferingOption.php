<?php

namespace App\Actions\Catalog;

use App\Models\DishOffering;
use App\Models\OfferingOption;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Catalog\CatalogEligibility;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class SaveOfferingOption
{
    public function __construct(private AuditLogger $audit, private CatalogEligibility $eligibility) {}

    public function handle(User $actor, DishOffering $offering, array $data, ?OfferingOption $option = null): OfferingOption
    {
        return DB::transaction(function () use ($actor, $offering, $data, $option) {
            $offering = DishOffering::lockForUpdate()->findOrFail($offering->id);
            $option = $option ? OfferingOption::lockForUpdate()->findOrFail($option->id) : new OfferingOption;
            Gate::forUser($actor->fresh())->authorize($option->exists ? 'update' : 'create', $option->exists ? $option : OfferingOption::class);
            abort_if($option->exists && $option->offering_id !== $offering->id, 404);
            if (! $option->exists || $data['active']) {
                $this->eligibility->ensureOptionCanActivate($offering);
            }
            $before = $option->exists ? $option->only('offering_id', 'option_code', 'label', 'unit', 'quantity', 'serving_pax', 'serving_claim', 'active') : null;
            $option->fill(collect($data)->except('option_code')->all());
            if (! $option->exists) {
                $option->offering_id = $offering->id;
                $option->option_code = $data['option_code'] ?? 'serving_'.strtolower((string) Str::ulid());
            }
            $option->save();
            $this->audit->record($before ? 'catalog.option_updated' : 'catalog.option_created', $actor, $option,
                $before, $option->only('offering_id', 'option_code', 'label', 'unit', 'quantity', 'serving_pax', 'serving_claim', 'active'));

            return $option;
        });
    }
}
