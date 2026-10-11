<?php

use App\Actions\Package\PublishVariantVersion;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\VariantVersion;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->loadEnvironmentFrom('.env.testing');
$app->make(Kernel::class)->bootstrap();

if (! $app->environment('testing') || ! str_ends_with(config('database.connections.pgsql.database'), '_test')) {
    throw new RuntimeException('Upgrade verification requires the isolated test database.');
}

$product = DB::table('products')->where('code', 'legacy_package')->first();
$roleId = DB::table('roles')->where('code', 'admin')->value('id')
    ?? DB::table('roles')->insertGetId(['code' => 'admin', 'name' => 'Administrator', 'created_at' => now(), 'updated_at' => now()]);
DB::table('user_roles')->insertOrIgnore(['user_id' => 1, 'role_id' => $roleId]);
foreach (DB::table('permissions')->pluck('id') as $permissionId) {
    DB::table('role_permissions')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $permissionId]);
}
$variant = DB::table('product_variants')->where('product_id', $product->id)->first();
$version = DB::table('variant_versions')->where('variant_id', $variant->id)->first();
$group = DB::table('selection_groups')->where('version_id', $version->id)->first();
$option = DB::table('group_options')->where('group_id', $group->id)->first();

if (! $product || ! $variant || ! $version || ! $group || ! $option
    || $version->status !== 'draft' || $group->slot_kind !== 'dish' || $option->price_adjustment_minor !== 0
    || ! Schema::hasColumn('variant_versions', 'published_snapshot')
    || ! Schema::hasColumn('products', 'archived_at')) {
    throw new RuntimeException('Legacy IDs, configuration, or Phase 4 columns were not preserved.');
}

$published = app(PublishVariantVersion::class)->handle(
    User::findOrFail(1),
    VariantVersion::findOrFail($version->id),
    1,
);
if ($published->status !== 'published' || ProductVariant::findOrFail($variant->id)->current_version_id !== $published->id) {
    throw new RuntimeException('Legacy package configuration could not be published after upgrade.');
}

echo json_encode([
    'legacy_ids_preserved' => true,
    'product_active' => (bool) $product->active,
    'version_status' => $published->fresh()->status,
    'group_slot' => $group->slot_kind,
    'legacy_configuration_publishable' => true,
    'phase4_migration' => DB::table('migrations')->where('migration', '2026_10_11_000011_allow_configurable_package_inclusions')->exists(),
], JSON_THROW_ON_ERROR);
