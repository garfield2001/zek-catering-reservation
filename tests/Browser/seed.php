<?php

use App\Actions\Package\PublishPackageVersion;
use App\Actions\Package\SavePackageMenuConfiguration;
use App\Actions\Package\SavePackageVersion;
use App\Actions\Package\SaveProduct;
use App\Actions\Payments\RecordPayment;
use App\Actions\Payments\ReviewPayment;
use App\Actions\Quotation\CreateManualQuotation;
use App\Actions\Quotation\IssueQuotation;
use App\Actions\Quotation\RecordQuotationAcceptance;
use App\Models\Dish;
use App\Models\DishCategory;
use App\Models\PaymentMethod;
use App\Models\ProductFamily;
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

// Browser specs intentionally rebuild one shared, isolated database. Coordinate
// that destructive reset with the guarded HTTP server so an in-flight request
// can never observe a partially rebuilt schema.
const BROWSER_SCHEMA_LOCK = 71005421;
$app['db']->selectOne('select pg_advisory_lock(?)', [BROWSER_SCHEMA_LOCK]);

try {
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
    if (getenv('BROWSER_RESERVATION_CONFIRMATION') === '1') {
        $admin = User::findOrFail($ids['admin']);
        $quotation = app(CreateManualQuotation::class)->handle($admin, [
            'customer_name' => 'Reservation Review Customer '.$run,
            'customer_mobile' => '09171234567',
            'customer_email' => null,
            'event_date' => now('Asia/Manila')->addDays(20)->toDateString(),
            'event_start_time' => '10:00',
            'venue_information' => 'Browser review venue',
            'event_notes' => null,
            'lines' => [['description' => 'Browser catering', 'quantity' => 1, 'unit_price' => '5000.00']],
        ]);
        $version = app(IssueQuotation::class)->handle($admin, $quotation->currentVersion)['version'];
        app(RecordQuotationAcceptance::class)->online($version, 'Reservation Review Customer', 'browser acceptance');
        $method = PaymentMethod::where('code', 'cash')->firstOrFail();
        $method->update(['is_active' => true]);
        $payment = app(RecordPayment::class)->handle($admin, $quotation, [
            'payment_method_id' => $method->id,
            'amount_minor' => 100000,
            'paid_at' => now(),
            'transaction_reference' => null,
            'notes' => 'Browser confirmation fixture.',
            'idempotency_key' => (string) str()->uuid(),
        ], 'administrator_exception', null, false);
        app(ReviewPayment::class)->verify($admin, $payment, $version);
    }
    if (getenv('BROWSER_STOREFRONT') === '1') {
        $admin = User::findOrFail($ids['admin']);
        $family = ProductFamily::where('code', 'main_catering')->sole();
        $riceCategory = DishCategory::create(['code' => 'browser_public_rice', 'name' => 'Browser Rice', 'active' => true]);
        $chickenCategory = DishCategory::create(['code' => 'browser_public_chicken', 'name' => 'Browser Chicken', 'active' => true]);
        $rice = Dish::create(['code' => 'browser_public_fried_rice', 'name' => 'Browser Fried Rice', 'category_id' => $riceCategory->id, 'active' => true]);
        $garlicRice = Dish::create(['code' => 'browser_public_garlic_rice', 'name' => 'Browser Garlic Rice', 'category_id' => $riceCategory->id, 'active' => true]);
        $chicken = Dish::create(['code' => 'browser_public_chicken_food', 'name' => 'Browser Chicken', 'category_id' => $chickenCategory->id, 'active' => true]);
        $product = app(SaveProduct::class)->handle($admin, ['family_id' => $family->id, 'name' => 'Browser Celebration Package', 'description' => 'Published browser fixture', 'active' => true]);
        $draft = $product->versions()->sole();
        app(SavePackageVersion::class)->handle($admin, $product, ['base_price_pesos' => '750.00', 'selection_policy' => 'uniform', 'minimum_quantity' => 150, 'quantity_step' => 1, 'maximum_quantity' => null, 'total_selection_count' => 2, 'draft_revision' => $draft->draft_revision], $draft);
        $draft->refresh();
        app(SavePackageMenuConfiguration::class)->handle($admin, $draft, ['draft_revision' => $draft->draft_revision, 'total_selection_count' => 2, 'food_item_ids' => [$rice->id, $garlicRice->id, $chicken->id], 'required_category_ids' => [$riceCategory->id]]);
        $draft->refresh();
        app(PublishPackageVersion::class)->handle($admin, $draft, $draft->draft_revision);
    }
    echo json_encode($ids);
} finally {
    $app['db']->selectOne('select pg_advisory_unlock(?)', [BROWSER_SCHEMA_LOCK]);
}
