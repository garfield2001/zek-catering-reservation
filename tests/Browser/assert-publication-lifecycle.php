<?php

use App\Models\Product;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->loadEnvironmentFrom('.env.testing');
$app->make(Kernel::class)->bootstrap();

if (! $app->environment('testing') || ! str_ends_with(config('database.connections.pgsql.database'), '_test')) {
    throw new RuntimeException('Browser assertions require the isolated test database.');
}

$run = getenv('BROWSER_RUN');
$product = Product::query()
    ->where('name', 'Lifecycle package '.$run)
    ->with(['variants.versions'])
    ->sole();
$variant = $product->variants->sole();
$published = $variant->versions->where('status', 'published')->sole();
$revision = $variant->versions->where('version_number', 2)->sole();

if (! $product->archived_at || $product->active || $published->version_number !== 1 || $revision->status !== 'abandoned') {
    throw new RuntimeException('Persisted package lifecycle state is invalid.');
}

echo json_encode([
    'published_version' => $published->version_number,
    'cloned_version' => $revision->version_number,
    'cloned_status' => $revision->status,
    'archived' => (bool) $product->archived_at,
    'active' => (bool) $product->active,
], JSON_THROW_ON_ERROR);
