<?php

// Read-only browser assertions: never expose connection credentials or session data.
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->loadEnvironmentFrom('.env.testing');
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || $app->configurationIsCached() || config('database.default') !== 'pgsql'
    || config('database.connections.pgsql.database') !== 'zek_catering_test'
    || config('database.connections.pgsql.host') !== '127.0.0.1'
    || (string) config('database.connections.pgsql.port') !== '5432') {
    throw new RuntimeException('Browser assertions require isolated testing configuration.');
}
$identity = DB::selectOne('select current_database() as name, inet_server_port() as port');
if ($identity->name !== 'zek_catering_test' || (int) $identity->port !== 5432) {
    throw new RuntimeException('Unexpected actual browser assertion database.');
}
$table = $argv[1] ?? '';
if (! in_array($table, ['dishes', 'product_families', 'products'], true) || ! isset($argv[2])) {
    throw new InvalidArgumentException('Provide an allowed catalog table and exact record name.');
}
echo DB::table($table)->where('name', $argv[2])->count();
