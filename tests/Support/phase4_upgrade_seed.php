<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Support\LegacyCollections;

require __DIR__.'/../../vendor/autoload.php';
require __DIR__.'/LegacyCollections.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->loadEnvironmentFrom('.env.testing');
$app->make(Kernel::class)->bootstrap();
LegacyCollections::seed();

if (! $app->environment('testing') || ! str_ends_with(config('database.connections.pgsql.database'), '_test')) {
    throw new RuntimeException('Upgrade fixtures require the isolated test database.');
}

$now = now();
$adminId = DB::table('users')->where('email', 'admin@example.test')->value('id')
    ?? DB::table('users')->orderBy('id')->value('id');
if (! $adminId) {
    $adminId = DB::table('users')->insertGetId([
        'name' => 'Upgrade Admin', 'email' => 'admin@example.test',
        'password' => Hash::make('Upgrade-Secret123!'),
        'active' => true, 'access_version' => 1, 'created_at' => $now, 'updated_at' => $now,
    ]);
}
$familyId = DB::table('product_families')->where('code', 'main_catering')->value('id');
$categoryId = DB::table('dish_categories')->insertGetId([
    'code' => 'upgrade_category', 'name' => 'Upgrade Category', 'sort_order' => 1,
    'active' => true, 'created_at' => $now, 'updated_at' => $now,
]);
$dishId = DB::table('dishes')->insertGetId([
    'category_id' => $categoryId, 'code' => 'upgrade_dish', 'name' => 'Upgrade Dish',
    'active' => true, 'created_at' => $now, 'updated_at' => $now,
]);
DB::table('dish_family_eligibilities')->insert([
    'dish_id' => $dishId, 'family_id' => $familyId, 'active' => true,
    'created_at' => $now, 'updated_at' => $now,
]);
$offeringId = DB::table('dish_offerings')->insertGetId([
    'dish_id' => $dishId, 'family_id' => $familyId, 'code' => 'upgrade_preparation',
    'name' => 'Upgrade Preparation', 'active' => true, 'created_at' => $now, 'updated_at' => $now,
]);
$optionId = DB::table('offering_options')->insertGetId([
    'offering_id' => $offeringId, 'option_code' => 'regular', 'label' => 'Regular',
    'unit' => 'tray', 'quantity' => '1.000', 'active' => true,
    'created_at' => $now, 'updated_at' => $now,
]);
$productId = DB::table('products')->insertGetId([
    'family_id' => $familyId, 'code' => 'legacy_package', 'name' => 'Legacy Package',
    'description' => 'Created before Phase 4', 'active' => true,
    'created_at' => $now, 'updated_at' => $now,
]);
$variantId = DB::table('product_variants')->insertGetId([
    'product_id' => $productId, 'code' => 'default', 'name' => 'Standard', 'active' => true,
    'created_at' => $now, 'updated_at' => $now,
]);
$versionId = DB::table('variant_versions')->insertGetId([
    'variant_id' => $variantId, 'family_id' => $familyId, 'version_number' => 1,
    'status' => 'draft', 'draft_revision' => 1, 'base_price_minor' => 125000,
    'currency' => 'PHP', 'price_basis' => 'per_pax', 'selection_policy' => 'uniform',
    'minimum_quantity' => 50, 'quantity_step' => 1, 'created_by' => $adminId,
    'created_at' => $now, 'updated_at' => $now,
]);
$groupId = DB::table('selection_groups')->insertGetId([
    'version_id' => $versionId, 'group_code' => 'main_dish', 'name' => 'Main Dish',
    'mode' => 'select_many', 'slot_kind' => 'dish', 'minimum_count' => 1,
    'maximum_count' => 2, 'unique_dishes' => true, 'category_mode' => 'free',
    'inclusion_count' => '1.000', 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now,
]);
$groupOptionId = DB::table('group_options')->insertGetId([
    'group_id' => $groupId, 'offering_option_id' => $optionId, 'is_default' => false,
    'price_adjustment_minor' => 0, 'created_at' => $now, 'updated_at' => $now,
]);

echo json_encode(compact('productId', 'variantId', 'versionId', 'groupId', 'groupOptionId'), JSON_THROW_ON_ERROR);
