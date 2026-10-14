<?php

use App\Actions\Package\PublishPackageVersion;
use App\Models\PackageVersion;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->loadEnvironmentFrom('.env.testing');
$app->make(Kernel::class)->bootstrap();

if ($app->configurationIsCached() || config('database.default') !== 'pgsql' || config('database.connections.pgsql.database') !== 'zek_catering_test' || (string) config('database.connections.pgsql.port') !== '5432') {
    throw new RuntimeException('Unsafe test database.');
}

$options = getopt('', ['user:', 'version:', 'revision:']);
$actor = User::findOrFail((int) $options['user']);
$version = PackageVersion::findOrFail((int) $options['version']);
$published = app(PublishPackageVersion::class)->handle($actor, $version, (int) $options['revision']);
echo json_encode(['id' => $published->id, 'status' => $published->status]);
