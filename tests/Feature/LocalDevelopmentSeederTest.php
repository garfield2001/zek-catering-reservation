<?php

namespace Tests\Feature;

use App\Models\Dish;
use App\Models\DishCategory;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\LocalDevelopmentAdministratorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LocalDevelopmentSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        // Laravel reuses its application between test classes. The explicit
        // local-bootstrap flag must not turn ordinary feature seeds into demo
        // seeds after this focused test has completed.
        config()->set('app.seed_local_development_data', false);

        parent::tearDown();
    }

    public function test_normal_local_bootstrap_creates_the_known_admin_and_demo_records_idempotently(): void
    {
        config()->set('app.seed_local_development_data', true);

        $this->seed(DatabaseSeeder::class);

        $admin = User::query()->where('email', LocalDevelopmentAdministratorSeeder::EMAIL)->sole();
        $this->assertSame('Admin Tester', $admin->name);
        $this->assertTrue($admin->active);
        $this->assertTrue($admin->roles()->where('code', 'admin')->exists());
        $this->assertTrue(Hash::check(LocalDevelopmentAdministratorSeeder::PASSWORD, $admin->password));
        $this->post('/login', [
            'email' => LocalDevelopmentAdministratorSeeder::EMAIL,
            'password' => LocalDevelopmentAdministratorSeeder::PASSWORD,
        ])->assertRedirect(route('admin.home'));
        $this->assertSame(2, Role::count());
        $this->assertSame(11, DishCategory::where('code', 'like', 'category_demo_%')->count());
        $this->assertSame(42, Dish::where('code', 'like', 'food_demo_%')->count());
        $this->assertSame(4, Product::whereNotNull('creation_token')->count());
        $this->assertSame(4, Product::whereNotNull('creation_token')->whereNull('current_version_id')->count());

        $admin->update(['password' => Hash::make('changed-local-password')]);
        $package = Product::where('name', 'Package A')->sole();
        $package->update(['name' => 'Locally renamed package']);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, User::where('email', LocalDevelopmentAdministratorSeeder::EMAIL)->count());
        $this->assertTrue(Hash::check('changed-local-password', $admin->fresh()->password));
        $this->assertSame('Locally renamed package', $package->fresh()->name);
        $this->assertSame(11, DishCategory::where('code', 'like', 'category_demo_%')->count());
        $this->assertSame(42, Dish::where('code', 'like', 'food_demo_%')->count());
        $this->assertSame(4, Product::whereNotNull('creation_token')->count());
    }

    public function test_normal_test_seed_does_not_create_known_credentials_or_demo_commercial_data(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(0, User::where('email', LocalDevelopmentAdministratorSeeder::EMAIL)->count());
        $this->assertSame(0, DishCategory::where('code', 'like', 'category_demo_%')->count());
        $this->assertSame(0, Dish::where('code', 'like', 'food_demo_%')->count());
        $this->assertSame(0, Product::whereNotNull('creation_token')->count());
    }
}
