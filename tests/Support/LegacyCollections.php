<?php

namespace Tests\Support;

use App\Models\FamilyOrderDefault;
use App\Models\ProductFamily;
use Illuminate\Support\Facades\DB;

class LegacyCollections
{
    public static function seed(): void
    {
        DB::transaction(function (): void {
            foreach ([
                'main_catering' => ['Main Catering Packages', false, 'main_package', 150, true, 10],
                'belly_lechon' => ['Belly Lechon Packages', true, 'package', 1, false, 20],
                'food_pack' => ['Food Pack Lunch', true, 'packed_meal', 10, false, 30],
                'food_tray' => ['Food Tray Menu', true, 'direct_sale', 1, false, 40],
                'pica_pica' => ['Pica-Pica', true, 'fixed_bundle', 1, false, 50],
            ] as $code => [$name, $isAddon, $behavior, $minimum, $floor, $order]) {
                $family = ProductFamily::where('code', $code)->first() ?? ProductFamily::forceCreate([
                    'code' => $code,
                    'name' => $name, 'is_addon' => $isAddon, 'behavior_type' => $behavior,
                    'active' => true, 'sort_order' => $order,
                ]);
                if (! $family->orderDefault()->exists()) {
                    FamilyOrderDefault::forceCreate(['family_id' => $family->id,
                        'minimum_quantity' => $minimum, 'quantity_step' => 1,
                        'maximum_quantity' => null, 'enforce_minimum_floor' => $floor]);
                }
            }
        });
    }
}
