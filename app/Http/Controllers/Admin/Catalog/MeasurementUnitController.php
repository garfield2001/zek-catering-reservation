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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Response;

class MeasurementUnitController extends Controller
{
    public function index(Request $request, MeasurementUnitUsage $usage): Response|JsonResponse|RedirectResponse
    {
        Gate::authorize('viewAny', MeasurementUnit::class);
        $units = MeasurementUnit::orderBy('name')->get()->map(fn (MeasurementUnit $unit) => [
            ...$unit->only('id', 'name', 'abbreviation', 'active'),
            'usage_count' => array_sum($usage->counts($unit)),
        ]);
        if ($request->expectsJson()) {
            return response()->json(['units' => $units, 'canManage' => Gate::allows('create', MeasurementUnit::class)]);
        }

        return redirect()->route('catalog.workspace')->with('status', 'Manage Measurement Units from the Food Catalog.');
    }

    public function store(SaveMeasurementUnitRequest $request, SaveMeasurementUnit $save): RedirectResponse|JsonResponse
    {
        $unit = $save->handle($request->user(), $request->validated());
        if ($request->expectsJson()) {
            return response()->json(['unit' => $unit->only('id', 'name', 'abbreviation', 'active')], 201);
        }

        return redirect()->route('catalog.units.index')->with('status', 'Measurement Unit created.');
    }

    public function update(SaveMeasurementUnitRequest $request, MeasurementUnit $unit, SaveMeasurementUnit $save): RedirectResponse|JsonResponse
    {
        $unit = $save->handle($request->user(), $request->validated(), $unit);
        if ($request->expectsJson()) {
            return response()->json(['unit' => $unit->only('id', 'name', 'abbreviation', 'active')]);
        }

        return redirect()->route('catalog.units.index')->with('status', 'Measurement Unit saved. Existing published measurements remain unchanged.');
    }

    public function destroy(DeleteMeasurementUnitRequest $request, MeasurementUnit $unit, DeleteMeasurementUnit $delete): RedirectResponse|JsonResponse
    {
        $delete->handle($request->user(), $unit);
        if ($request->expectsJson()) {
            return response()->json(status: 204);
        }

        return redirect()->route('catalog.units.index')->with('status', 'Measurement Unit deleted.');
    }
}
