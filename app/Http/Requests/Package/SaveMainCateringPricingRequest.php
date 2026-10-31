<?php

namespace App\Http\Requests\Package;

use App\Models\PackageVersion;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;

class SaveMainCateringPricingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $product = $this->route('product');

        return $product instanceof Product
            && $product->family->behavior_type === 'main_package'
            && $this->user()->can('update', $product)
            && $this->user()->can('price', PackageVersion::class);
    }

    public function rules(): array
    {
        return PackageInputRules::pricing();
    }
}
