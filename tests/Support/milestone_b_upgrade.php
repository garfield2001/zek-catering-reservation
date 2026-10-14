<?php

// Destructive integration verification for the isolated test database ONLY.
use App\Actions\Catalog\EnsurePrimaryService;
use App\Actions\Package\PublishPackageVersion;
use App\Actions\Package\SavePackageMenuConfiguration;
use App\Actions\Package\SavePackageVersion;
use App\Actions\Package\SaveProduct;
use App\Models\Dish;
use App\Models\DishCategory;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
$baseline = array_values(array_filter(glob(database_path('migrations/*.php')), fn ($path) => ! str_contains(basename($path), '2026_10_14_')));
Artisan::call('migrate:fresh', ['--path' => $baseline, '--realpath' => true, '--force' => true]);
Artisan::call('db:seed', ['--force' => true]);
$admin = User::factory()->create();
$admin->roles()->attach(Role::where('code', 'admin')->sole());
$family = app(EnsurePrimaryService::class)->handle();
$category = DishCategory::create(['code' => 'upgrade_rice', 'name' => 'Upgrade Rice', 'active' => true]);
$food = Dish::create(['code' => 'upgrade_food', 'name' => 'Upgrade Fried Rice', 'active' => true, 'category_id' => $category->id]);
$food->eligibleFamilies()->attach($family->id, ['active' => true]);
$product = app(SaveProduct::class)->handle($admin, ['family_id' => $family->id, 'name' => 'Upgrade Package', 'active' => true]);
$version = $product->versions()->sole();
$version = app(SavePackageVersion::class)->handle($admin, $product, ['base_price_pesos' => '850.00', 'selection_policy' => 'uniform', 'minimum_quantity' => 150, 'quantity_step' => 10, 'maximum_quantity' => null, 'draft_revision' => $version->draft_revision], $version);
app(SavePackageMenuConfiguration::class)->handle($admin, $version, ['total_selection_count' => 1, 'food_item_ids' => [$food->id], 'required_category_ids' => [$category->id], 'draft_revision' => $version->draft_revision]);
$version->refresh();
app(PublishPackageVersion::class)->handle($admin, $version, $version->draft_revision);
$snapshot = $version->fresh()->published_snapshot;
$ids = [$admin->id, $family->id, $category->id, $food->id, $product->id, $version->id];
if (Artisan::call('migrate', ['--force' => true]) !== 0) {
    throw new RuntimeException('Forward migrations failed.');
}
if (Product::findOrFail($ids[4])->current_version_id !== $ids[5] || $version->fresh()->published_snapshot !== $snapshot
    || User::findOrFail($ids[0])->id !== $admin->id || Dish::findOrFail($ids[3])->name !== 'Upgrade Fried Rice'
    || ! Schema::hasTable('individual_listings') || ! Schema::hasTable('listing_versions')
    || ! Schema::hasColumn('products', 'creation_hash') || ! Schema::hasColumn('dishes', 'image_path')
    || Schema::hasTable('product_variants') || Schema::hasTable('variant_versions')) {
    throw new RuntimeException('Milestone B upgrade did not preserve the expected schema and records.');
}
echo json_encode(['database' => $identity->name, 'port' => (int) $identity->port, 'migrations' => DB::table('migrations')->count(), 'preserved_ids' => $ids, 'published_snapshot_unchanged' => true, 'individual_listing_tables' => true, 'variant_tables_absent' => true], JSON_THROW_ON_ERROR).PHP_EOL;
