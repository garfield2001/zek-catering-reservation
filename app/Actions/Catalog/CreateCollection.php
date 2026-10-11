<?php

namespace App\Actions\Catalog;

use App\Enums\Catalog\CollectionBehavior;
use App\Models\FamilyOrderDefault;
use App\Models\ProductFamily;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateCollection
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(User $actor, array $data): ProductFamily
    {
        return DB::transaction(function () use ($actor, $data) {
            // Existing Admin role serializes competing primary-creation requests.
            Role::where('code', 'admin')->lockForUpdate()->firstOrFail();
            Gate::forUser($actor->fresh())->authorize('manage', ProductFamily::class);
            $isAddon = $data['kind'] === 'addon';
            $behavior = CollectionBehavior::from($data['behavior_type']);
            if (($isAddon && $behavior === CollectionBehavior::MainPackage)
                || (! $isAddon && $behavior !== CollectionBehavior::MainPackage)) {
                throw ValidationException::withMessages(['behavior_type' => 'Choose a supported configuration type for this collection role.']);
            }
            if (! $isAddon && ProductFamily::where('is_addon', false)->whereNull('archived_at')->exists()) {
                throw ValidationException::withMessages(['kind' => 'A current primary catering collection already exists. Add package variants there.']);
            }
            if (ProductFamily::whereNull('archived_at')->whereRaw('lower(btrim(name)) = lower(btrim(?))', [$data['name']])->exists()) {
                throw ValidationException::withMessages(['name' => 'This collection name is already in use.']);
            }
            $family = new ProductFamily;
            $family->forceFill([
                'code' => 'collection_'.strtolower((string) Str::ulid()),
                'name' => $data['name'], 'description' => $data['description'] ?? null,
                'sort_order' => (int) ProductFamily::max('sort_order') + 1, 'active' => $data['active'],
                'is_addon' => $isAddon, 'behavior_type' => $behavior->value,
            ])->save();
            $default = new FamilyOrderDefault;
            $default->family_id = $family->id;
            $default->fill([
                'minimum_quantity' => $isAddon ? 1 : 150,
                'quantity_step' => 1,
                'maximum_quantity' => null,
                'enforce_minimum_floor' => ! $isAddon,
            ])->save();
            $this->audit->record('catalog.collection_created', $actor, $family, null,
                $family->only('code', 'name', 'description', 'behavior_type', 'is_addon', 'sort_order', 'active'));

            return $family;
        });
    }
}
