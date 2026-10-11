<?php

use App\Actions\Package\PublishVariantVersion;
use App\Models\User;
use App\Models\VariantVersion;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->loadEnvironmentFrom('.env.testing');
$app->make(Kernel::class)->bootstrap();

$options = getopt('', ['user:', 'version:', 'revision:']);
$actor = User::findOrFail((int) $options['user']);
$version = VariantVersion::findOrFail((int) $options['version']);
$published = app(PublishVariantVersion::class)->handle($actor, $version, (int) $options['revision']);
echo json_encode(['id' => $published->id, 'status' => $published->status]);
