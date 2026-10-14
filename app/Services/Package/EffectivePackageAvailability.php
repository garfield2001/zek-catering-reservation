<?php

namespace App\Services\Package;

use App\Models\Product;
use Illuminate\Validation\ValidationException;

class EffectivePackageAvailability
{
    public function __construct(private ConfigurationFeasibility $feasibility, private PackageMenuConfiguration $main) {}

    /** @return array{available: bool, reasons: array<int, string>} */
    public function forProduct(Product $product): array
    {
        $product->loadMissing('family', 'currentVersion');
        $reasons = [];
        if ($product->family->archived_at !== null || ! $product->family->active) {
            $reasons[] = 'The service is disabled or archived.';
        }
        if ($product->archived_at !== null || ! $product->active) {
            $reasons[] = 'The package is disabled or archived.';
        }
        if (! $product->currentVersion || $product->currentVersion->status !== 'published') {
            $reasons[] = 'No current published configuration exists.';
        } else {
            try {
                if (isset($product->currentVersion->snapshot()['menu'])) {
                    $this->main->validatePublishedAvailability($product->currentVersion);
                } else {
                    $this->feasibility->find($product->currentVersion);
                }
            } catch (ValidationException $exception) {
                $reasons = [...$reasons, ...array_values($exception->errors())[0]];
            }
        }

        return ['available' => $reasons === [], 'reasons' => $reasons];
    }
}
