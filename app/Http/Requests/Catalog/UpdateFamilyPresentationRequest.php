<?php

namespace App\Http\Requests\Catalog;

use App\Models\ProductFamily;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class UpdateFamilyPresentationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage', ProductFamily::class);
    }

    protected function prepareForValidation(): void
    {
        foreach (['name', 'description'] as $field) {
            if (is_string($this->input($field))) {
                $value = trim($this->input($field));
                $this->merge([$field => $field === 'description' && $value === '' ? null : $value]);
            }
        }
    }

    public function rules(): array
    {
        $family = $this->route('family');

        return [
            'name' => ['bail', 'required', 'string', 'max:255', function (string $attribute, mixed $value, \Closure $fail) use ($family): void {
                $query = DB::table('product_families')->whereNull('archived_at')->whereRaw('lower(btrim(name)) = lower(btrim(?))', [$value]);
                if ($family instanceof ProductFamily) {
                    $query->where('id', '!=', $family->id);
                }
                if ($query->exists()) {
                    $fail('This collection name is already in use.');
                }
            }],
            'description' => ['nullable', 'string', 'max:5000'],
            'sort_order' => ['required', 'integer', 'between:-2147483648,2147483647'],
            'active' => ['required', 'boolean'],
        ];
    }
}
