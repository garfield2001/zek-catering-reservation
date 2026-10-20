<?php

namespace App\Actions\Catalog;

use App\Models\MeasurementUnit;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Catalog\MeasurementUnitUsage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class DeleteMeasurementUnit
{
    public function __construct(private AuditLogger $audit, private MeasurementUnitUsage $usage) {}

    public function handle(User $actor, MeasurementUnit $unit): void
    {
        DB::transaction(function () use ($actor, $unit): void {
            $unit = MeasurementUnit::lockForUpdate()->findOrFail($unit->id);
            Gate::forUser($actor->fresh())->authorize('delete', $unit);
            if ($this->usage->isUsed($unit)) {
                throw ValidationException::withMessages([
                    'unit' => "This unit is used by {$this->usage->summary($unit)}. Deactivate it instead so existing packages and published history remain available.",
                ]);
            }
            $before = $unit->only('id', 'name', 'abbreviation', 'active');
            $unit->delete();
            $this->audit->record('catalog.measurement_unit_deleted', $actor, $unit, $before, null);
        });
    }
}
