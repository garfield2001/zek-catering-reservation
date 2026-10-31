<?php

namespace App\Http\Requests\Package;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;

class SaveMainCateringMenuRequest extends FormRequest
{
    public function authorize(): bool
    {
        $product = $this->route('product');

        return $product instanceof Product
            && $product->family->behavior_type === 'main_package'
            && $this->user()->can('update', $product);
    }

    public function rules(): array
    {
        return PackageInputRules::menu();
    }
}
