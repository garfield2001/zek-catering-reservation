<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LegacyCollections;
use Tests\TestCase;

class PackageScopeNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_package_index_preserves_primary_and_addon_scope_in_guided_navigation(): void
    {
        $this->seed(DatabaseSeeder::class);
        LegacyCollections::seed();
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('code', 'admin')->sole());

        $this->actingAs($admin)->withSession(['access_version' => $admin->access_version])
            ->get('/admin/packages/products?scope=addon')
            ->assertRedirect(route('services.index'));
    }
}
