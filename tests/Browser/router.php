<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));
$assetPath = realpath(__DIR__.'/../../public'.(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/'));
$publicPath = realpath(__DIR__.'/../../public');
if ($assetPath && str_starts_with($assetPath, $publicPath.DIRECTORY_SEPARATOR) && is_file($assetPath) && pathinfo($assetPath, PATHINFO_EXTENSION) !== 'php') {
    return false;
}

// A direct PHP server process lets Playwright own its complete lifecycle on Windows.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || $app->configurationIsCached()
    || config('database.default') !== 'pgsql'
    || config('database.connections.pgsql.database') !== 'zek_catering_test'
    || (string) config('database.connections.pgsql.port') !== '5432'
    || config('database.connections.pgsql.host') !== '127.0.0.1') {
    http_response_code(503);
    exit('Unsafe browser test environment.');
}
// Never attach browser regression tests to an unrelated running Vite dev server.
$testHotFile = storage_path('framework/browser-tests-disabled.hot');
if (is_file($testHotFile)) {
    throw new RuntimeException('Unexpected browser test hot-file marker.');
}
$app->make(Vite::class)->useHotFile($testHotFile);
header('X-Catering-Test-Database: zek_catering_test:5432');
$app->handleRequest(Request::capture());
