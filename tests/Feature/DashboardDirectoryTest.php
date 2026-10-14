<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardDirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_dashboard_does_not_expose_unpermitted_catalog_counts(): void
    {
        $this->seed(DatabaseSeeder::class);
        $staff = User::factory()->create();
        $staff->roles()->attach(Role::where('code', 'staff')->sole());
        $this->actingAs($staff)->withSession(['access_version' => $staff->access_version])->get('/admin')
            ->assertInertia(fn ($page) => $page->where('setup.categories', null)->where('setup.items', null)->where('setup.products', null));
    }

    public function test_directory_filters_orders_and_rejects_unsupported_sort_columns(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('code', 'admin')->sole());
        User::factory()->create(['name' => 'Searchable Alpha', 'active' => true]);
        User::factory()->create(['name' => 'Searchable Beta', 'active' => true]);
        User::factory()->create(['name' => 'Searchable Gamma', 'active' => false]);
        $this->actingAs($admin)->withSession(['access_version' => $admin->access_version]);
        $this->get('/admin/staff?q=searchable&status=active&sort=name&direction=desc')
            ->assertInertia(fn ($page) => $page->where('users.total', 2)->where('users.data.0.name', 'Searchable Beta')->where('users.data.1.name', 'Searchable Alpha'));
        $this->get('/admin/staff?sort=password')->assertSessionHasErrors('sort');
    }
}
