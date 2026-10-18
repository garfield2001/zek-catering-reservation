<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\LegacyCollections;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->loadEnvironmentFrom('.env.testing');
$app->make(Kernel::class)->bootstrap();

if (! $app->environment('testing') || $app->configurationIsCached() || config('database.default') !== 'pgsql' || config('database.connections.pgsql.database') !== 'zek_catering_test' || (string) config('database.connections.pgsql.port') !== '5432' || config('database.connections.pgsql.host') !== '127.0.0.1') {
    throw new RuntimeException('Browser fixtures require the isolated test database.');
}
$identity = $app['db']->selectOne('select current_database() as name, inet_server_port() as port');
if ($identity->name !== 'zek_catering_test' || (int) $identity->port !== 5432) {
    throw new RuntimeException('Browser fixtures refused an unexpected actual database connection.');
}
Artisan::call('migrate:fresh', ['--force' => true]);
Artisan::call('db:seed', ['--force' => true]);
if (getenv('BROWSER_LEGACY') !== '0') {
    LegacyCollections::seed();
}
$run = getenv('BROWSER_RUN');
$password = getenv('BROWSER_PASSWORD');
if (! $run || ! $password) {
    throw new RuntimeException('Missing ephemeral fixture credentials.');
}
$ids = [];
foreach (['admin', 'staff'] as $role) {
    $user = User::create(['name' => 'Browser '.ucfirst($role), 'email' => $role.'-'.$run.'@example.test', 'password' => $password]);
    $user->roles()->attach(Role::where('code', $role)->sole());
    $ids[$role] = $user->id;
}
echo json_encode($ids);
