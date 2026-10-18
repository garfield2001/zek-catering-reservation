<?php

// Compatibility entry point: current commercial-refinement upgrade, test database ONLY.
use App\Actions\Catalog\CloneListingDraft;
use App\Actions\Catalog\CreateCollection;
use App\Actions\Catalog\EnsurePrimaryService;
use App\Actions\Catalog\SaveIndividualListing;
use App\Actions\Package\PublishPackageVersion;
use App\Actions\Package\SavePackageMenuConfiguration;
use App\Actions\Package\SavePackageVersion;
use App\Actions\Package\SaveProduct;
use App\Models\Dish;
use App\Models\DishCategory;
use App\Models\DishOffering;
use App\Models\IndividualListing;
use App\Models\ListingVersion;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\Catalog\CatalogAvailability;
use App\Services\Catalog\ListingConfiguration;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->loadEnvironmentFrom('.env.testing');
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || $app->configurationIsCached() || config('database.default') !== 'pgsql'
    || config('database.connections.pgsql.database') !== 'zek_catering_test' || config('database.connections.pgsql.host') !== '127.0.0.1'
    || (string) config('database.connections.pgsql.port') !== '5432') {
    throw new RuntimeException('Upgrade verification requires the isolated PostgreSQL test database.');
}
$identity = DB::selectOne('select current_database() as name, inet_server_port() as port');
if ($identity->name !== 'zek_catering_test' || (int) $identity->port !== 5432) {
    throw new RuntimeException('Unexpected actual database connection.');
}
$baseline = array_values(array_filter(glob(database_path('migrations/*.php')), fn ($path) => ! str_contains(basename($path), '000017_use_global_food_availability') && ! str_contains(basename($path), '000019_create_listing_selling_options') && ! str_contains(basename($path), '000021_allow_configurable_listing_units')));
Artisan::call('migrate:fresh', ['--path' => $baseline, '--realpath' => true, '--force' => true]);
Artisan::call('db:seed', ['--force' => true]);
$admin = User::factory()->create();
$admin->roles()->attach(Role::where('code', 'admin')->sole());
$family = app(EnsurePrimaryService::class)->handle();
$category = DishCategory::create(['code' => 'upgrade_rice', 'name' => 'Upgrade Rice', 'active' => true]);
$food = Dish::create(['code' => 'upgrade_food', 'name' => 'Upgrade Fried Rice', 'active' => true, 'category_id' => $category->id]);
$product = app(SaveProduct::class)->handle($admin, ['family_id' => $family->id, 'name' => 'Upgrade Package', 'active' => true]);
$version = $product->versions()->sole();
$version = app(SavePackageVersion::class)->handle($admin, $product, ['base_price_pesos' => '850.00', 'selection_policy' => 'uniform', 'minimum_quantity' => 150, 'quantity_step' => 10, 'maximum_quantity' => null, 'draft_revision' => $version->draft_revision], $version);
app(SavePackageMenuConfiguration::class)->handle($admin, $version, ['total_selection_count' => 1, 'food_item_ids' => [$food->id], 'required_category_ids' => [$category->id], 'draft_revision' => $version->draft_revision]);
$version->refresh();
app(PublishPackageVersion::class)->handle($admin, $version, $version->draft_revision);
$snapshot = $version->fresh()->published_snapshot;
$ids = [$admin->id, $family->id, $category->id, $food->id, $product->id, $version->id];
// Representative immutable schema-1 listing before grouped selling options exist.
$saleFamily = app(CreateCollection::class)->handle($admin, ['kind' => 'addon', 'behavior_type' => 'direct_sale', 'name' => 'Upgrade Trays', 'active' => true]);
$listing = IndividualListing::create(['family_id' => $saleFamily->id, 'dish_id' => $food->id, 'code' => 'legacy_listing', 'creation_token' => (string) Str::uuid(), 'creation_hash' => str_repeat('a', 64), 'active' => true]);
$listingSnapshot = ['schema' => 1, 'listing_id' => $listing->id, 'version_number' => 1,
    'service' => ['name' => $saleFamily->name], 'food' => ['name' => $food->name], 'category' => ['id' => $category->id, 'name' => $category->name],
    'serving_option' => null, 'name' => 'Legacy Rice Tray', 'description' => null, 'selling_unit' => 'tray', 'price_minor' => '65000', 'currency' => 'PHP',
    'minimum_quantity' => 1, 'quantity_step' => 1, 'maximum_quantity' => null];
// Seed the historical schema directly: Eloquent caches guardable columns for the
// process lifetime, whereas a real deployment restarts workers after migration.
$legacyListingVersionId = DB::table('listing_versions')->insertGetId(['listing_id' => $listing->id, 'version_number' => 1, 'draft_revision' => 1, 'status' => 'published',
    'name' => 'Legacy Rice Tray', 'selling_unit' => 'tray', 'price_minor' => 65000, 'currency' => 'PHP', 'minimum_quantity' => 1, 'quantity_step' => 1,
    'published_snapshot' => json_encode($listingSnapshot, JSON_THROW_ON_ERROR), 'published_at' => now(), 'published_by' => $admin->id]);
$legacyListingVersion = ListingVersion::findOrFail($legacyListingVersionId);
$listing->update(['current_version_id' => $legacyListingVersion->id]);
$listingSnapshot = $legacyListingVersion->fresh()->snapshot();
// Reconstruct retired assignment keys; the fresh baseline no longer creates them.
DB::unprepared(<<<'SQL'
    CREATE TABLE dish_family_eligibilities (
        dish_id bigint NOT NULL REFERENCES dishes(id) ON DELETE RESTRICT,
        family_id bigint NOT NULL REFERENCES product_families(id) ON DELETE RESTRICT,
        active boolean NOT NULL DEFAULT true,
        created_at timestamptz NULL, updated_at timestamptz NULL,
        PRIMARY KEY (dish_id, family_id)
    );
    CREATE INDEX dish_family_eligibilities_family_id_active_index ON dish_family_eligibilities (family_id, active);
    INSERT INTO dish_family_eligibilities (dish_id, family_id, active) SELECT DISTINCT dish_id, family_id, true FROM individual_listings;
    ALTER TABLE dish_offerings DROP CONSTRAINT dish_offerings_dish_id_foreign;
    ALTER TABLE dish_offerings DROP CONSTRAINT dish_offerings_family_id_foreign;
    ALTER TABLE dish_offerings ADD CONSTRAINT dish_offerings_eligibility_fk FOREIGN KEY (dish_id, family_id) REFERENCES dish_family_eligibilities(dish_id, family_id) ON DELETE RESTRICT;
    ALTER TABLE individual_listings ADD CONSTRAINT individual_listings_dish_id_family_id_foreign FOREIGN KEY (dish_id, family_id) REFERENCES dish_family_eligibilities(dish_id, family_id) ON DELETE RESTRICT;
    SQL);
DB::table('dish_family_eligibilities')->insert(['dish_id' => $food->id, 'family_id' => $family->id, 'active' => false]);
$offering = DishOffering::create(['dish_id' => $food->id, 'family_id' => $family->id, 'code' => 'retained', 'name' => 'Retained offering', 'active' => true]);
$offeringId = $offering->id;
if (Artisan::call('migrate', ['--force' => true]) !== 0) {
    throw new RuntimeException('Forward migrations failed.');
}
if ($legacyListingVersion->fresh()->snapshot() !== $listingSnapshot || $listing->fresh()->current_version_id !== $legacyListingVersion->id
    || ! Schema::hasTable('listing_selling_options')
    || app(ListingConfiguration::class)->preview($listing, $legacyListingVersion, 2)['total_minor'] !== '130000') {
    throw new RuntimeException('Legacy listing history or pricing changed during grouped-option upgrade.');
}
$draft = app(CloneListingDraft::class)->handle($admin, $listing, $legacyListingVersion);
app(SaveIndividualListing::class)->handle($admin, [
    'family_id' => $saleFamily->id, 'dish_id' => $food->id, 'offering_option_id' => null, 'version_id' => $draft->id, 'draft_revision' => 1,
    'name' => '', 'selling_unit' => 'serving basket', 'minimum_quantity' => 1, 'quantity_step' => 1, 'maximum_quantity' => null,
    'selling_options' => [['label' => 'Small Tray', 'price_pesos' => '650'], ['label' => 'Large Tray', 'price_pesos' => '1100']],
], $listing);
if ($draft->fresh()->selling_unit !== 'serving basket' || $draft->sellingOptions()->count() !== 2 || $legacyListingVersion->fresh()->snapshot() !== $listingSnapshot) {
    throw new RuntimeException('Grouped Draft conversion did not preserve the legacy publication.');
}
if (Product::findOrFail($ids[4])->current_version_id !== $ids[5] || $version->fresh()->published_snapshot !== $snapshot
    || User::findOrFail($ids[0])->id !== $admin->id || Dish::findOrFail($ids[3])->name !== 'Upgrade Fried Rice'
    || ! Schema::hasTable('individual_listings') || ! Schema::hasTable('listing_versions')
    || ! Schema::hasColumn('products', 'creation_hash') || ! Schema::hasColumn('dishes', 'image_path')
    || Schema::hasTable('product_variants') || Schema::hasTable('variant_versions') || Schema::hasTable('dish_family_eligibilities')
    || $offering->fresh()->id !== $offeringId
    || ! app(CatalogAvailability::class)->dishEligibleForFamily($food, $family)) {
    throw new RuntimeException('Commercial upgrade did not preserve the expected schema and records.');
}
echo json_encode(['database' => $identity->name, 'port' => (int) $identity->port, 'migrations' => DB::table('migrations')->count(), 'preserved_ids' => $ids, 'published_snapshot_unchanged' => true, 'individual_listing_tables' => true, 'variant_tables_absent' => true], JSON_THROW_ON_ERROR).PHP_EOL;
