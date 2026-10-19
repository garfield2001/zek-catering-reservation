<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequestNormalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_malformed_login_email_types_return_validation_errors(): void
    {
        foreach ([[], ['nested' => 'email'], true, 123] as $email) {
            $this->postJson('/login', ['email' => $email, 'password' => 'Invalid-test-password1'])->assertUnprocessable()->assertJsonValidationErrors('email');
            $this->assertGuest();
        }
    }

    public function test_malformed_account_email_types_do_not_create_users(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('code', 'admin')->sole());
        $this->actingAs($admin)->withSession(['access_version' => $admin->access_version]);
        foreach ([[], ['nested' => 'email'], true, 123] as $email) {
            $this->postJson('/admin/staff', ['name' => 'Test member', 'email' => $email, 'active' => true, 'role' => 'staff', 'password' => 'Valid-test-password1', 'password_confirmation' => 'Valid-test-password1'])
                ->assertUnprocessable()->assertJsonValidationErrors('email');
        }
        $this->assertSame(1, User::count());
    }

    public function test_valid_email_normalization_is_preserved_for_login_and_account_creation(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::factory()->create(['email' => 'normalization@example.test', 'password' => 'Valid-test-password1']);
        $admin->roles()->attach(Role::where('code', 'admin')->sole());
        $this->post('/login', ['email' => ' NORMALIZATION@EXAMPLE.TEST ', 'password' => 'Valid-test-password1'])->assertSessionHasNoErrors()->assertRedirect('/admin/home');
        $this->assertAuthenticatedAs($admin);
        $this->post('/admin/staff', ['name' => 'Test member', 'email' => ' MEMBER@EXAMPLE.TEST ', 'active' => true, 'role' => 'staff', 'password' => 'Valid-test-password1', 'password_confirmation' => 'Valid-test-password1'])->assertSessionHasNoErrors()->assertRedirect('/admin/staff');
        $this->assertDatabaseHas('users', ['email' => 'member@example.test']);
    }
}
