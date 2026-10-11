<?php

namespace Tests\Feature;

use App\Actions\Access\SaveUser;
use App\Models\AuditEvent;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Access\PermissionResolver;
use App\Services\Audit\AuditLogger;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class AccessFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function user(string $role = 'staff', bool $active = true): User
    {
        $user = User::factory()->create(['active' => $active]);
        $user->roles()->attach(Role::where('code', $role)->sole());

        return $user;
    }

    private function signIn(User $user): static
    {
        return $this->actingAs($user)->withSession(['access_version' => $user->access_version]);
    }

    private function grant(User $user, string $code, string $effect = 'allow'): void
    {
        $user->permissionOverrides()->syncWithoutDetaching([Permission::where('code', $code)->sole()->id => ['effect' => $effect]]);
        $user->unsetRelations();
    }

    private function payload(User $user, array $changes = []): array
    {
        return [...$user->only('name', 'email', 'active', 'access_version'), 'role' => $user->isAdmin() ? 'admin' : 'staff', ...$changes];
    }

    public function test_guests_are_redirected_and_public_registration_is_absent(): void
    {
        $this->get('/admin')->assertRedirect('/login');
        $this->get('/admin/staff')->assertRedirect('/login');
        $this->get('/register')->assertNotFound();
        $this->post('/register')->assertNotFound();
        $this->get('/forgot-password')->assertNotFound();
        $this->get('/login')->assertOk();
    }

    public function test_valid_login_rotates_session_and_is_audited_and_logout_invalidates_it(): void
    {
        $user = $this->user();
        $this->withSession(['marker' => true]);
        $old = session()->getId();
        $this->post('/login', ['email' => strtoupper($user->email), 'password' => 'test-password-only'])->assertRedirect('/admin/home');
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($old, session()->getId());
        $this->assertSame($user->access_version, session('access_version'));
        $this->assertDatabaseHas('audit_events', ['action' => 'auth.login', 'actor_id' => $user->id]);
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
        $this->assertDatabaseHas('audit_events', ['action' => 'auth.logout']);
    }

    public function test_wrong_credentials_and_inactive_accounts_use_same_generic_error(): void
    {
        $user = $this->user(active: false);
        foreach (['wrong', 'test-password-only'] as $password) {
            $this->post('/login', ['email' => $user->email, 'password' => $password])->assertSessionHasErrors('email');
            $this->assertGuest();
        }
        $this->assertDatabaseMissing('audit_events', ['action' => 'auth.login']);
    }

    public function test_login_is_throttled_after_five_failed_attempts(): void
    {
        $this->freezeTime();
        $user = $this->user();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong']);
        }
        $this->post('/login', ['email' => $user->email, 'password' => 'test-password-only'])
            ->assertSessionHasErrors(['email' => 'Too many attempts. Try again in 60 seconds.']);
        $this->assertGuest();
    }

    public function test_staff_default_access_is_dashboard_only_and_forged_requests_are_denied(): void
    {
        $staff = $this->user();
        $this->signIn($staff)->get('/admin')->assertOk();
        $this->get('/admin/staff')->assertForbidden();
        $this->post('/admin/staff', [])->assertForbidden();
        $this->get('/admin/audit')->assertForbidden();
        $this->put('/admin/staff/'.$staff->id.'/permissions', ['access_version' => 1, 'overrides' => []])->assertForbidden();
    }

    public function test_permission_precedence_and_admin_bypass_do_not_allow_inactive_accounts(): void
    {
        $user = $this->user();
        $resolver = app(PermissionResolver::class);
        $this->assertTrue($resolver->allows($user, 'dashboard.view'));
        $this->grant($user, 'dashboard.view', 'deny');
        $this->assertFalse($resolver->allows($user, 'dashboard.view'));
        $this->grant($user, 'audit.view');
        $this->assertTrue($resolver->allows($user, 'audit.view'));
        $admin = $this->user('admin');
        $this->grant($admin, 'audit.view', 'deny');
        $this->assertTrue($resolver->allows($admin, 'audit.view'));
        $admin->active = false;
        $this->assertFalse($resolver->allows($admin, 'audit.view'));
    }

    public function test_staff_denied_dashboard_is_sent_to_an_authorized_section_after_login(): void
    {
        $staff = $this->user();
        $this->grant($staff, 'dashboard.view', 'deny');
        $this->grant($staff, 'catalog.view');

        $this->get('/admin')->assertRedirect('/login');
        $this->post('/login', ['email' => $staff->email, 'password' => 'test-password-only'])
            ->assertRedirect('/admin/home');
        $this->get('/admin/home')->assertRedirect('/admin/catalog/dishes');
        $this->get('/admin')->assertForbidden();
        $this->get('/admin/staff')->assertForbidden();
    }

    public function test_active_staff_without_a_workspace_permission_gets_a_safe_landing_page(): void
    {
        $staff = $this->user();
        $this->grant($staff, 'dashboard.view', 'deny');

        $this->signIn($staff)->get('/admin/home')->assertOk();
        $this->get('/admin')->assertForbidden();
    }

    public function test_admin_can_create_staff_with_hashed_password_and_safe_audit(): void
    {
        $admin = $this->user('admin');
        $this->signIn($admin)->post('/admin/staff', [
            'name' => 'New Staff', 'email' => 'NEW@example.test', 'role' => 'staff', 'active' => true,
            'password' => 'New-Secret1234', 'password_confirmation' => 'New-Secret1234',
        ])->assertRedirect('/admin/staff');
        $created = User::where('email', 'new@example.test')->sole();
        $this->assertTrue(Hash::check('New-Secret1234', $created->password));
        $event = AuditEvent::where('action', 'access.user_created')->sole();
        $this->assertStringNotContainsString('New-Secret', $event->toJson());
        $this->assertArrayNotHasKey('password', $event->after_data);
    }

    public function test_permission_changes_are_audited_and_revoke_existing_sessions(): void
    {
        $admin = $this->user('admin');
        $staff = $this->user();
        DB::table('sessions')->insert(['id' => 'old-session', 'user_id' => $staff->id, 'payload' => '', 'last_activity' => time()]);
        $this->signIn($admin)->put('/admin/staff/'.$staff->id.'/permissions', [
            'access_version' => 1, 'overrides' => [['code' => 'audit.view', 'effect' => 'allow']],
        ])->assertRedirect('/admin/staff');
        $this->assertSame(2, $staff->fresh()->access_version);
        $this->assertDatabaseMissing('sessions', ['id' => 'old-session']);
        $this->assertDatabaseHas('audit_events', ['action' => 'access.permissions_updated']);
        $this->actingAs($staff->fresh())->withSession(['access_version' => 1])->get('/admin')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_inactive_admin_is_logged_out(): void
    {
        $this->signIn($this->user('admin', false))->get('/admin')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_last_active_admin_cannot_be_deactivated_or_demoted(): void
    {
        $admin = $this->user('admin');
        $this->signIn($admin)->put('/admin/staff/'.$admin->id, $this->payload($admin, ['active' => false]))->assertSessionHasErrors('active');
        $this->put('/admin/staff/'.$admin->id, $this->payload($admin, ['role' => 'staff']))->assertSessionHasErrors('active');
        $this->assertTrue($admin->fresh()->active);
        $this->assertDatabaseMissing('audit_events', ['action' => 'access.user_updated']);
    }

    public function test_administrator_transfer_requires_another_active_admin_and_explicit_confirmation(): void
    {
        $first = $this->user('admin');
        $second = $this->user('admin');
        $this->signIn($second)->put('/admin/staff/'.$first->id,
            $this->payload($first, ['active' => false]))->assertSessionHasErrors('confirm_access_change');
        $this->assertTrue($first->fresh()->active);

        $this->put('/admin/staff/'.$first->id,
            $this->payload($first, ['active' => false, 'confirm_access_change' => true]))->assertRedirect('/admin/home');
        $this->assertFalse($first->fresh()->active);
        $this->assertDatabaseHas('audit_events', ['action' => 'access.user_updated', 'subject_id' => $first->id]);

        $this->put('/admin/staff/'.$second->id,
            $this->payload($second, ['role' => 'staff', 'confirm_access_change' => true]))->assertSessionHasErrors('active');
        $this->assertTrue($second->fresh()->isAdmin());
    }

    public function test_admin_overrides_cannot_revoke_full_administrative_access(): void
    {
        $admin = $this->user('admin');
        $this->grant($admin, 'access.manage', 'deny');
        $this->grant($admin, 'staff.manage', 'deny');
        $this->assertTrue(app(PermissionResolver::class)->allows($admin, 'access.manage'));
        $this->assertTrue(app(PermissionResolver::class)->allows($admin, 'staff.manage'));
        $this->signIn($admin)->get('/admin/staff')->assertOk();
        $this->get('/admin/staff/'.$admin->id.'/permissions')->assertUnprocessable();
        $this->put('/admin/staff/'.$admin->id.'/permissions', [
            'access_version' => $admin->access_version,
            'overrides' => [['code' => 'access.manage', 'effect' => 'deny']],
        ])->assertUnprocessable();
        $this->assertDatabaseMissing('audit_events', ['action' => 'access.permissions_updated', 'subject_id' => $admin->id]);
    }

    public function test_administrator_can_transfer_role_to_another_active_administrator(): void
    {
        $first = $this->user('admin');
        $second = $this->user('admin');

        $this->signIn($second)->put('/admin/staff/'.$first->id,
            $this->payload($first, ['role' => 'staff', 'confirm_access_change' => true]))->assertRedirect('/admin/home');
        $this->assertFalse($first->fresh()->isAdmin());
        $this->assertTrue($second->fresh()->isAdmin());
        $this->assertDatabaseHas('audit_events', ['action' => 'access.user_updated', 'subject_id' => $first->id]);
    }

    public function test_stale_edit_and_duplicate_email_are_rejected(): void
    {
        $admin = $this->user('admin');
        $staff = $this->user();
        $this->signIn($admin)->put('/admin/staff/'.$staff->id, $this->payload($staff, ['access_version' => 99]))->assertConflict();
        $this->put('/admin/staff/'.$staff->id, $this->payload($staff, ['email' => strtoupper($admin->email)]))->assertSessionHasErrors('email');
    }

    public function test_delegated_staff_cannot_assign_admin_or_edit_more_privileged_users(): void
    {
        $actor = $this->user();
        $target = $this->user();
        $this->grant($actor, 'staff.manage');
        $this->grant($target, 'audit.view');
        $this->signIn($actor)->put('/admin/staff/'.$target->id, $this->payload($target))->assertForbidden();
        $this->post('/admin/staff', ['name' => 'Escalation', 'email' => 'escalation@example.test', 'role' => 'admin', 'active' => true, 'password' => 'New-Secret1234', 'password_confirmation' => 'New-Secret1234'])->assertSessionHasErrors('role');
    }

    public function test_delegated_staff_cannot_grant_unowned_permissions_or_modify_self(): void
    {
        $actor = $this->user();
        $target = $this->user();
        $this->grant($actor, 'access.manage');
        $this->signIn($actor)->put('/admin/staff/'.$target->id.'/permissions', [
            'access_version' => 1, 'overrides' => [['code' => 'audit.view', 'effect' => 'allow']],
        ])->assertForbidden();
        $this->put('/admin/staff/'.$actor->id.'/permissions', ['access_version' => 1, 'overrides' => []])->assertForbidden();
        $this->assertSame(1, $target->fresh()->access_version);
    }

    public function test_denials_are_logged_without_a_success_audit(): void
    {
        Log::spy();
        $this->signIn($this->user())->get('/admin/audit')->assertForbidden();
        Log::shouldHaveReceived('warning')->withArgs(fn ($message, $context) => $message === 'Authorization denied' && isset($context['request_id']))->once();
        $this->assertDatabaseCount('audit_events', 0);
    }

    public function test_audit_failure_rolls_back_account_mutation(): void
    {
        $admin = $this->user('admin');
        $staff = $this->user();
        $this->mock(AuditLogger::class)->shouldReceive('record')->andThrow(new \RuntimeException('audit unavailable'));
        try {
            app(SaveUser::class)->handle($admin, $this->payload($staff, ['name' => 'Changed']), $staff);
            $this->fail('Expected audit failure');
        } catch (\RuntimeException $exception) {
            $this->assertSame('audit unavailable', $exception->getMessage());
        }
        $this->assertSame($staff->name, $staff->fresh()->name);
        $this->assertSame(1, $staff->fresh()->access_version);
    }

    public function test_audit_redaction_is_recursive(): void
    {
        $event = app(AuditLogger::class)->record('test.redaction', null, null, null, ['safe' => 'yes', 'nested' => ['password' => 'secret', 'token' => 'secret', 'name' => 'safe']]);
        $this->assertSame(['safe' => 'yes', 'nested' => ['name' => 'safe']], $event->after_data);
    }

    public function test_inactive_privileged_staff_cannot_be_taken_over_by_lesser_staff(): void
    {
        $actor = $this->user();
        $target = $this->user(active: false);
        $this->grant($actor, 'staff.manage');
        $this->grant($target, 'access.manage');
        $this->signIn($actor)->put('/admin/staff/'.$target->id, $this->payload($target, ['active' => true]))
            ->assertForbidden();
        $this->assertFalse($target->fresh()->active);
    }

    public function test_staff_cannot_create_an_account_with_role_grants_they_are_denied(): void
    {
        $actor = $this->user();
        $this->grant($actor, 'staff.manage');
        $this->grant($actor, 'dashboard.view', 'deny');
        $this->signIn($actor)->post('/admin/staff', [])->assertForbidden();
    }

    public function test_bootstrap_command_creates_first_admin_without_a_seeded_password(): void
    {
        $this->artisan('app:create-admin')
            ->expectsQuestion('Name', 'Initial Admin')
            ->expectsQuestion('Email', 'initial@example.test')
            ->expectsQuestion('Password (12+ characters, mixed case and numbers)', 'Initial-Secret123')
            ->expectsOutput('Admin created.')
            ->assertSuccessful();
        $user = User::where('email', 'initial@example.test')->sole();
        $this->assertTrue($user->isAdmin());
        $this->assertTrue(app(PermissionResolver::class)->allows($user, 'staff.manage'));
        $this->assertTrue(app(PermissionResolver::class)->allows($user, 'access.manage'));
        $this->assertDatabaseHas('audit_events', ['action' => 'access.admin_bootstrapped', 'subject_id' => $user->id]);
    }

    public function test_bootstrap_command_reports_missing_administrator_role_without_creating_account(): void
    {
        Role::where('code', 'admin')->delete();
        $this->artisan('app:create-admin')
            ->expectsQuestion('Name', 'Initial Admin')
            ->expectsQuestion('Email', 'missing-role@example.test')
            ->expectsQuestion('Password (12+ characters, mixed case and numbers)', 'Initial-Secret123')
            ->expectsOutput('The Administrator role is missing. Run php artisan db:seed --class=DatabaseSeeder, then retry.')
            ->assertFailed();
        $this->assertDatabaseMissing('users', ['email' => 'missing-role@example.test']);
    }

    public function test_audit_database_rejects_update_delete_and_truncate(): void
    {
        app(AuditLogger::class)->record('test.append_only', null);
        foreach (['UPDATE audit_events SET action = \'tampered\'', 'DELETE FROM audit_events', 'TRUNCATE audit_events'] as $sql) {
            try {
                DB::transaction(fn () => DB::statement($sql));
                $this->fail('Expected append-only rejection');
            } catch (QueryException $exception) {
                $this->assertStringContainsString('append-only', $exception->getMessage());
            }
        }
    }

    public function test_database_constraints_reject_invalid_override_and_case_duplicate_email(): void
    {
        $staff = $this->user();
        foreach ([
            fn () => DB::table('user_permission_overrides')->insert(['user_id' => $staff->id, 'permission_id' => Permission::first()->id, 'effect' => 'invalid']),
            fn () => User::factory()->create(['email' => strtoupper($staff->email)]),
            fn () => DB::table('user_roles')->insert(['user_id' => 999999, 'role_id' => Role::first()->id]),
        ] as $invalid) {
            try {
                DB::transaction($invalid);
                $this->fail('Expected database constraint rejection');
            } catch (QueryException $exception) {
                $this->assertNotEmpty($exception->getSql());
            }
        }
    }
}
