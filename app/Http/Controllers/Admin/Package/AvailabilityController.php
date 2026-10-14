<?php

namespace App\Http\Controllers\Admin\Package;

use App\Actions\Package\ChangePackageAvailability;
use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AvailabilityController extends Controller
{
    public function product(Request $request, Product $product, ChangePackageAvailability $action): RedirectResponse
    {
        Gate::authorize('lifecycle', $product);
        $data = $request->validate(['operation' => ['required', 'in:disable,enable,archive'],
            'confirm' => ['exclude_unless:operation,archive', 'required', 'accepted']]);
        $action->handle($request->user(), $product, $data['operation']);

        return redirect()->route('packages.show', $product)->with('status', 'Package Product availability updated.');
    }
}
