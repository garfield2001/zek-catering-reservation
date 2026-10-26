<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/** Development fixtures invoked by the explicitly local DatabaseSeeder path. */
class DevelopmentDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DevelopmentFoodCatalogSeeder::class,
            DevelopmentMainCateringPackageSeeder::class,
        ]);
    }
}
