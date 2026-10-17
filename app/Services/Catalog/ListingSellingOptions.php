<?php

namespace App\Services\Catalog;

use App\Models\ListingVersion;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ListingSellingOptions
{
    public function __construct(private MinorMoney $money) {}

    public function normalize(array $input): array
    {
        Validator::make(['selling_options' => $input], [
            'selling_options' => ['required', 'array', 'list', 'min:1', 'max:100'],
            'selling_options.*' => ['array:label,price_pesos'],
            'selling_options.*.label' => ['required', 'string', 'max:100'],
            'selling_options.*.price_pesos' => ['required', 'string', 'max:20', 'regex:/^(0|[1-9][0-9]*)(?:\.[0-9]{1,2})?$/'],
        ])->validate();
        $seen = [];
        $options = [];
        foreach ($input as $index => $row) {
            $label = trim($row['label']);
            $key = mb_strtolower($label);
            if ($label === '' || isset($seen[$key])) {
                throw ValidationException::withMessages(["selling_options.$index.label" => 'Give each selling option a distinct, nonempty name.']);
            }
            $seen[$key] = true;
            $options[] = ['label' => $label, 'price_minor' => (string) $this->money->fromPesos($row['price_pesos'], "selling_options.$index.price_pesos"), 'sort_order' => $index];
        }

        return $options;
    }

    public function facts(ListingVersion $version): array
    {
        return $version->sellingOptions()->get()->map(fn ($option) => $option->only(['label', 'price_minor', 'sort_order']))->all();
    }
}
