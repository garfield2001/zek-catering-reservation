<?php

// Destructive representative upgrade verification: isolated testing database ONLY.
use App\Actions\Catalog\CreateCollection;
use App\Actions\Package\ClonePackageDraft;
use App\Actions\Package\PublishPackageVersion;
use App\Actions\Package\SavePackageMenuConfiguration;
use App\Actions\Package\SaveProduct;
use App\Models\Dish;
use App\Models\DishCategory;
use App\Models\MeasurementUnit;
use App\Models\Role;
use App\Models\User;
use App\Services\Package\PackagePresentation;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->loadEnvironmentFrom('.env.testing');
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || $app->configurationIsCached() || config('database.default') !== 'pgsql'
    || config('database.connections.pgsql.database') !== 'zek_catering_test' || config('database.connections.pgsql.host') !== '127.0.0.1'
    || (string) config('database.connections.pgsql.port') !== '5432') {
    throw new RuntimeException('Presentation upgrade verification requires isolated PostgreSQL testing configuration.');
}
$identity = DB::selectOne('select current_database() as name, inet_server_port() as port');
if ($identity->name !== 'zek_catering_test' || (int) $identity->port !== 5432) {
    throw new RuntimeException('Unexpected actual database connection.');
}
$baseline = array_values(array_filter(glob(database_path('migrations/*.php')), fn ($path) => ! str_contains(basename($path), '000020_add_package_presentation') && ! str_contains(basename($path), '000021_allow_configurable_listing_units') && ! str_contains(basename($path), '000022_add_service_package_configuration')));
if (Artisan::call('migrate:fresh', ['--path' => $baseline, '--realpath' => true, '--force' => true]) !== 0) {
    throw new RuntimeException('Pre-presentation baseline failed.');
}
Artisan::call('db:seed', ['--force' => true]);
$admin = User::factory()->create();
$admin->roles()->attach(Role::where('code', 'admin')->sole());
$family = app(CreateCollection::class)->handle($admin, ['kind' => 'addon', 'name' => 'Legacy Meal Packs', 'behavior_type' => 'package', 'active' => true]);
$category = DishCategory::create(['name' => 'Upgrade Food', 'code' => 'presentation_upgrade', 'active' => true]);
$food = Dish::create(['name' => 'Legacy Rice', 'code' => 'presentation_rice', 'category_id' => $category->id, 'active' => true]);
$product = app(SaveProduct::class)->handle($admin, ['family_id' => $family->id, 'name' => 'Legacy Meal', 'active' => true]);
$version = $product->versions()->sole();
DB::table('package_version_food_items')->insert(['package_version_id' => $version->id, 'dish_id' => $food->id, 'is_fixed' => true, 'always_include' => true, 'price_adjustment_minor' => 0]);
// Seed actual old-schema commercial facts without invoking new-schema application writers.
$snapshot = ['schema' => 4, 'collection' => $family->only('id', 'name', 'code', 'behavior_type', 'description'),
    'product' => $product->only('id', 'name', 'code', 'description'), 'package' => $product->only('id', 'name', 'code', 'description'),
    'version' => ['id' => $version->id, 'number' => 1, 'base_price_minor' => '14900', 'currency' => 'PHP', 'price_basis' => 'per_pack', 'selection_policy' => 'uniform', 'minimum_quantity' => 10, 'quantity_step' => 1, 'maximum_quantity' => null, 'total_selection_count' => 1],
    'menu' => ['total_selection_count' => 1, 'serving_default' => null, 'food_sizes' => [], 'required_categories' => [],
        'food_items' => [['id' => $food->id, 'name' => $food->name, 'code' => $food->code, 'category_id' => $category->id, 'category_name' => $category->name, 'is_fixed' => true, 'always_include' => true, 'included_quantity' => null, 'included_unit' => null, 'price_adjustment_minor' => '0']]], 'groups' => []];
DB::table('package_versions')->where('id', $version->id)->update(['status' => 'published', 'base_price_minor' => 14900, 'price_basis' => 'per_pack', 'minimum_quantity' => 10, 'quantity_step' => 1, 'total_selection_count' => 1, 'published_snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR), 'published_at' => now(), 'published_by' => $admin->id]);
$product->forceFill(['current_version_id' => $version->id])->save();
$snapshot = $version->fresh()->snapshot();
$ids = [$admin->id, $family->id, $category->id, $food->id, $product->id, $version->id];
if (Artisan::call('migrate', ['--force' => true]) !== 0) {
    throw new RuntimeException('Presentation migration failed.');
}
$version->refresh();
if ($version->snapshot() !== $snapshot || $version->package_format !== null || app(PackagePresentation::class)->effective($version) !== null || $product->fresh()->current_version_id !== $version->id) {
    throw new RuntimeException('Upgrade changed historical commercial facts or inferred a format.');
}
$draft = app(ClonePackageDraft::class)->handle($admin, $product, $version);
$unit = MeasurementUnit::create(['name' => 'Meal Box', 'abbreviation' => 'meal-box', 'active' => true]);
app(SavePackageMenuConfiguration::class)->handle($admin, $draft, ['draft_revision' => $draft->draft_revision, 'total_selection_count' => 1, 'food_item_ids' => [$food->id], 'required_category_ids' => [], 'food_details' => [$food->id => ['is_fixed' => true]], 'presentation' => ['format' => 'combined_contents', 'quantity' => 1, 'measurement_unit_id' => $unit->id]]);
$draft->refresh();
app(PublishPackageVersion::class)->handle($admin, $draft, $draft->draft_revision);
if ($version->fresh()->snapshot() !== $snapshot || $draft->fresh()->snapshot()['presentation']['default']['measurement_unit']['name'] !== 'Meal Box') {
    throw new RuntimeException('Legacy revision failed to preserve the original publication.');
}
echo json_encode(['database' => $identity->name, 'port' => $identity->port, 'migrations' => DB::table('migrations')->count(), 'preserved_ids' => $ids, 'legacy_snapshot_unchanged' => true, 'legacy_format_unknown' => true, 'revision_published' => true], JSON_THROW_ON_ERROR).PHP_EOL;
