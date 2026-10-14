<?php

namespace App\Actions\Catalog;

use App\Models\FamilyOrderDefault;
use App\Models\ProductFamily;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class EnsurePrimaryService
{
    public function handle(): ProductFamily
    {
        return DB::transaction(function (): ProductFamily {
            // Serializes first-use/bootstrap requests without relying on a seeded numeric ID.
            DB::select('SELECT pg_advisory_xact_lock(73421, 1)');
            $existing = ProductFamily::where('is_addon', false)->whereNull('archived_at')->first();
            if ($existing) {
                return $existing; // Preserve existing identity, eligibility and deliberate disabled state.
            }
            $name = 'Catering Packages';
            if (ProductFamily::whereNull('archived_at')->whereRaw('lower(btrim(name)) = lower(?)', [$name])->exists()) {
                $name = 'Catering Packages '.Str::ulid();
            }
            $family = ProductFamily::forceCreate([
                'code' => ProductFamily::where('code', 'main_catering')->exists() ? 'catering_'.strtolower((string) Str::ulid()) : 'main_catering', 'name' => $name,
                'description' => null, 'is_addon' => false, 'behavior_type' => 'main_package',
                'active' => true, 'sort_order' => 0,
            ]);
            FamilyOrderDefault::forceCreate(['family_id' => $family->id, 'minimum_quantity' => 150,
                'quantity_step' => 1, 'maximum_quantity' => null, 'enforce_minimum_floor' => true]);
            app(AuditLogger::class)->record('catalog.primary_service_provisioned', null, $family, null,
                $family->only('code', 'name', 'behavior_type'));

            return $family;
        });
    }
}
