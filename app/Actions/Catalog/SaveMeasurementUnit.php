<?php

namespace App\Actions\Catalog;

use App\Models\MeasurementUnit;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SaveMeasurementUnit
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(User $actor, array $data, ?MeasurementUnit $unit = null): MeasurementUnit
    {
        return DB::transaction(function () use ($actor, $data, $unit): MeasurementUnit {
            Gate::forUser($actor->fresh())->authorize($unit ? 'update' : 'create', $unit ?? MeasurementUnit::class);
            // Serialize normalized uniqueness checks, including concurrent creates.
            DB::select('SELECT pg_advisory_xact_lock(?)', [715015]);
            $unit = $unit ? MeasurementUnit::lockForUpdate()->findOrFail($unit->id) : new MeasurementUnit;
            foreach (['name', 'abbreviation'] as $field) {
                if (MeasurementUnit::whereRaw("lower(btrim({$field})) = lower(btrim(?))", [$data[$field]])
                    ->when($unit->exists, fn ($query) => $query->whereKeyNot($unit->id))->exists()) {
                    throw ValidationException::withMessages([$field => 'This measurement unit '.$field.' is already in use.']);
                }
            }
            $before = $unit->exists ? $unit->only('name', 'abbreviation', 'active') : null;
            $unit->fill($data)->save();
            $this->audit->record($before ? 'catalog.measurement_unit_updated' : 'catalog.measurement_unit_created', $actor, $unit, $before, $unit->only('name', 'abbreviation', 'active'));

            return $unit;
        });
    }
}
