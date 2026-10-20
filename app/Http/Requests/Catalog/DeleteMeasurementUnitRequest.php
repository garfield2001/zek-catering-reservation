<?php

namespace App\Http\Requests\Catalog;

use App\Models\MeasurementUnit;
use Illuminate\Foundation\Http\FormRequest;

class DeleteMeasurementUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var MeasurementUnit $unit */
        $unit = $this->route('unit');

        return $this->user()->can('delete', $unit);
    }
}
