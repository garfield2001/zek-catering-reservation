<?php

namespace App\Services\Package;

use App\Models\ProductVariant;
use Illuminate\Validation\ValidationException;

class EffectivePackageAvailability
{
    public function __construct(private ConfigurationFeasibility $feasibility) {}

    /** @return array{available: bool, reasons: array<int, string>} */
    public function forVariant(ProductVariant $variant): array
    {
        $variant->loadMissing('product.family', 'currentVersion');
        $reasons = [];
        if ($variant->product->family->archived_at !== null || ! $variant->product->family->active) {
            $reasons[] = 'The collection is inactive or archived.';
        }
        if ($variant->product->archived_at !== null || ! $variant->product->active) {
            $reasons[] = 'The package product is disabled or archived.';
        }
        if ($variant->archived_at !== null || ! $variant->active) {
            $reasons[] = 'The package variant is disabled or archived.';
        }
        if (! $variant->currentVersion || $variant->currentVersion->status !== 'published') {
            $reasons[] = 'No current published configuration exists.';
        } else {
            try {
                $this->feasibility->find($variant->currentVersion);
            } catch (ValidationException $exception) {
                $reasons = [...$reasons, ...array_values($exception->errors())[0]];
            }
        }

        return ['available' => $reasons === [], 'reasons' => $reasons];
    }
}
