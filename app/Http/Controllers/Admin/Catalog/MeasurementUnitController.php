<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Actions\Catalog\DeleteMeasurementUnit;
use App\Actions\Catalog\SaveMeasurementUnit;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\DeleteMeasurementUnitRequest;
use App\Http\Requests\Catalog\SaveMeasurementUnitRequest;
use App\Models\MeasurementUnit;
use App\Services\Catalog\MeasurementUnitUsage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MeasurementUnitController extends Controller
{
    public function index(MeasurementUnitUsage $usage): Response
    {
        Gate::authorize('viewAny', MeasurementUnit::class);

        return Inertia::render('admin/catalog/measurement-units', [
            'units' => MeasurementUnit::orderBy('name')->get()->map(fn (MeasurementUnit $unit) => [
                ...$unit->only('id', 'name', 'abbreviation', 'active'),
                'usage_count' => array_sum($usage->counts($unit)),
            ]),
            'canManage' => Gate::allows('create', MeasurementUnit::class),
        ]);
    }

    public function store(SaveMeasurementUnitRequest $request, SaveMeasurementUnit $save): RedirectResponse|JsonResponse
    {
        $unit = $save->handle($request->user(), $request->validated());
        if ($request->expectsJson()) {
            return response()->json(['unit' => $unit->only('id', 'name', 'abbreviation', 'active')], 201);
        }

        return redirect()->route('catalog.units.index')->with('status', 'Measurement Unit created.');
    }

    public function update(SaveMeasurementUnitRequest $request, MeasurementUnit $unit, SaveMeasurementUnit $save): RedirectResponse
    {
        $save->handle($request->user(), $request->validated(), $unit);

        return redirect()->route('catalog.units.index')->with('status', 'Measurement Unit saved. Existing published measurements remain unchanged.');
    }

    public function destroy(DeleteMeasurementUnitRequest $request, MeasurementUnit $unit, DeleteMeasurementUnit $delete): RedirectResponse
    {
        $delete->handle($request->user(), $unit);

        return redirect()->route('catalog.units.index')->with('status', 'Measurement Unit deleted.');
    }
}
