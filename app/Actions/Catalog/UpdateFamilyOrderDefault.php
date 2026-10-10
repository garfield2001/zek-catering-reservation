<?php

namespace App\Actions\Catalog;

use App\Models\FamilyOrderDefault;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class UpdateFamilyOrderDefault
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(User $actor, FamilyOrderDefault $default, array $data): FamilyOrderDefault
    {
        return DB::transaction(function () use ($actor, $default, $data) {
            $default = FamilyOrderDefault::lockForUpdate()->findOrFail($default->family_id);
            Gate::forUser($actor->fresh())->authorize('update', $default);
            abort_if($default->family->archived_at !== null, 404);
            $before = $default->only('minimum_quantity', 'quantity_step', 'maximum_quantity', 'enforce_minimum_floor');
            $default->fill($data);
            $default->save();
            $this->audit->record('catalog.family_default_updated', $actor, $default,
                $before, $default->only('minimum_quantity', 'quantity_step', 'maximum_quantity', 'enforce_minimum_floor'));

            return $default;
        });
    }
}
