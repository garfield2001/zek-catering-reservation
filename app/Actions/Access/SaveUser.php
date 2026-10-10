<?php

namespace App\Actions\Access;

use App\Models\Role;
use App\Models\User;
use App\Services\Access\AccessMutation;
use App\Services\Access\AdministratorAuthority;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SaveUser
{
    public function __construct(private AccessMutation $access, private AdministratorAuthority $administrators, private AuditLogger $audit) {}

    public function handle(User $actor, array $data, ?User $target = null): User
    {
        return DB::transaction(function () use ($actor, $data, $target) {
            $this->access->lock();
            $actor = $actor->fresh();
            $target = $target ? User::lockForUpdate()->findOrFail($target->id) : null;
            Gate::forUser($actor)->authorize($target ? 'update' : 'create', $target ?? User::class);
            abort_unless($actor->isAdmin() || $data['role'] === 'staff', 403);
            if ($target) {
                abort_if($target->access_version !== (int) $data['access_version'], 409, 'Account changed. Reload before saving.');
                if ($target->active && $target->isAdmin() && (! $data['active'] || $data['role'] !== 'admin')) {
                    $this->administrators->assertCanRemove($target);
                    if (! ($data['confirm_access_change'] ?? false)) {
                        throw ValidationException::withMessages(['confirm_access_change' => 'Confirm the Administrator access transfer before saving.']);
                    }
                }
            }
            $before = $target ? $this->access->snapshot($target) : null;
            $user = $target ?? new User;
            $user->fill(collect($data)->only(['name', 'email', 'active'])->all());
            if (! empty($data['password'])) {
                $user->password = $data['password'];
            }
            $user->save();
            $user->roles()->sync([Role::where('code', $data['role'])->sole()->id]);
            if ($target) {
                $this->access->revoke($user);
            }
            $this->audit->record($target ? 'access.user_updated' : 'access.user_created', $actor, $user, $before, $this->access->snapshot($user));

            return $user;
        });
    }
}
