<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
DB::beginTransaction();
try {
    DB::statement('SET TRANSACTION READ ONLY');
    $identity = DB::selectOne('SELECT current_database() AS database, inet_server_port() AS port');
    $migrations = DB::table('migrations')->orderBy('id')->pluck('migration')->all();
    $counts = [];
    foreach (['users', 'dishes', 'dish_family_eligibilities', 'dish_offerings', 'offering_options', 'products', 'package_versions', 'selection_groups', 'individual_listings', 'listing_versions'] as $table) {
        $exists = DB::selectOne('SELECT to_regclass(?) AS name', ['public.'.$table])->name !== null;
        $counts[$table] = $exists ? DB::table($table)->count() : null;
    }
    echo json_encode(['identity' => $identity, 'migrations' => $migrations, 'counts' => $counts], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT).PHP_EOL;
} finally {
    DB::rollBack();
}
