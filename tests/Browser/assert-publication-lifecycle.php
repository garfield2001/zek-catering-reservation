<?php

use App\Models\Product;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->loadEnvironmentFrom('.env.testing');
$app->make(Kernel::class)->bootstrap();

if (! $app->environment('testing') || $app->configurationIsCached() || config('database.connections.pgsql.database') !== 'zek_catering_test' || (string) config('database.connections.pgsql.port') !== '5432') {
    throw new RuntimeException('Browser assertions require the isolated test database.');
}

$run = getenv('BROWSER_RUN');
$product = Product::query()
    ->where('name', 'Lifecycle package '.$run)
    ->with(['versions'])
    ->sole();
$published = $product->versions->where('status', 'published')->sole();
$revision = $product->versions->where('version_number', 2)->sole();

if (! $product->archived_at || $product->active || $published->version_number !== 1 || $revision->status !== 'abandoned') {
    throw new RuntimeException('Persisted package lifecycle state is invalid.');
}

echo json_encode([
    'published_price' => (string) $published->base_price_minor,
    'revision_price' => (string) $revision->base_price_minor,
    'fixed_food' => collect($published->snapshot()['menu']['food_items'])->where('is_fixed', true)->sole()['name'],
    'total' => $published->snapshot()['menu']['total_selection_count'],
    'published_version' => $published->version_number,
    'cloned_version' => $revision->version_number,
    'cloned_status' => $revision->status,
    'archived' => (bool) $product->archived_at,
    'active' => (bool) $product->active,
], JSON_THROW_ON_ERROR);
