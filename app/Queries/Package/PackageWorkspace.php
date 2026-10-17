<?php

namespace App\Queries\Package;

use App\Models\PackageVersion;
use App\Models\Product;
use App\Queries\Catalog\CommercialHistory;
use App\Services\Package\EffectivePackageAvailability;
use Illuminate\Support\Facades\Gate;

class PackageWorkspace
{
    public function data(Product $product): array
    {
        $product->load('family.orderDefault');
        $draft = $product->versions()->where('status', 'draft')->first(['id', 'package_product_id', 'status', 'draft_revision', 'version_number']);
        $canHistory = Gate::allows('history', PackageVersion::class);
        $history = $canHistory ? $product->versions()->where('status', '!=', 'draft')->reorder('version_number')->get() : collect();
        $availability = app(EffectivePackageAvailability::class)->forProduct($product);
        // Availability is computed server-side, but historical commercial data requires its own permission.
        $product->unsetRelation('currentVersion');

        return ['product' => $product, 'draft' => $draft, 'history' => $history, 'availability' => $availability,
            'commercialHistory' => $canHistory ? app(CommercialHistory::class)->forOwner($product) : null,
            'defaults' => $product->family->orderDefault,
            'canManage' => Gate::allows('update', $product), 'canPrice' => Gate::allows('price', PackageVersion::class),
            'canPublish' => Gate::allows('publish', PackageVersion::class), 'canHistory' => $canHistory,
            'canLifecycle' => Gate::allows('lifecycle', $product),

        ];
    }
}
