<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->loadEnvironmentFrom('.env.testing');
$app->make(Kernel::class)->bootstrap();
if ($app->configurationIsCached() || config('database.default') !== 'pgsql' || config('database.connections.pgsql.database') !== 'zek_catering_test' || (string) config('database.connections.pgsql.port') !== '5432') {
    throw new RuntimeException('Unsafe test connection.');
}
$db = DB::connection();
echo json_encode($db->selectOne('select current_database() as database, inet_server_port() as port'));
echo PHP_EOL.'Active test transactions: '.count($db->select('select pid from pg_stat_activity where datname = current_database() and pid <> pg_backend_pid() and xact_start is not null')).PHP_EOL;
