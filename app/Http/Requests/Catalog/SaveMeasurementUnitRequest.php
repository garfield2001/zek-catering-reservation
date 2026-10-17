<?php

namespace App\Http\Requests\Catalog;

use App\Models\MeasurementUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class SaveMeasurementUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        $unit = $this->route('unit');

        return $this->user()->can($unit ? 'update' : 'create', $unit ?? MeasurementUnit::class);
    }

    protected function prepareForValidation(): void
    {
        foreach (['name', 'abbreviation'] as $key) {
            if (is_string($this->input($key))) {
                $this->merge([$key => trim($this->input($key))]);
            }
        }
    }

    public function rules(): array
    {
        $unique = function (string $attribute, mixed $value, \Closure $fail): void {
            $query = DB::table('measurement_units')->whereRaw("lower(btrim({$attribute})) = lower(btrim(?))", [$value]);
            if ($this->route('unit')) {
                $query->where('id', '!=', $this->route('unit')->id);
            }
            if ($query->exists()) {
                $fail('This measurement unit '.$attribute.' is already in use.');
            }
        };

        return ['name' => ['bail', 'required', 'string', 'max:100', $unique],
            'abbreviation' => ['bail', 'required', 'string', 'max:20', $unique],
            'active' => ['required', 'boolean']];
    }
}
