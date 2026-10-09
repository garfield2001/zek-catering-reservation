<?php

namespace App\Actions\Access;

use App\Models\Permission;
use App\Models\User;
use App\Services\Access\AccessMutation;
use App\Services\Access\PermissionResolver;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class UpdatePermissions
{
    public function __construct(private AccessMutation $access, private PermissionResolver $permissions, private AuditLogger $audit) {}

    public function handle(User $actor, User $target, array $data): void
    {
        DB::transaction(function () use ($actor, $target, $data) {
            $this->access->lock();
            $actor = $actor->fresh();
            $target = User::lockForUpdate()->findOrFail($target->id);
            Gate::forUser($actor)->authorize('permissions', $target);
            abort_if($target->isAdmin(), 422, 'Admin already has full access; overrides apply to Staff only.');
            abort_if($target->access_version !== (int) $data['access_version'], 409, 'Account changed. Reload before saving.');
            $before = $this->access->snapshot($target);
            $overrides = collect($data['overrides'])->pluck('effect', 'code')->all();
            $rows = [];
            foreach (Permission::all() as $permission) {
                $old = $before['overrides'][$permission->code] ?? null;
                $new = $overrides[$permission->code] ?? null;
                if ($old !== $new) {
                    abort_unless($this->permissions->allows($actor, $permission->code), 403);
                }
                if ($new !== null) {
                    $rows[$permission->id] = ['effect' => $new];
                }
            }
            $target->permissionOverrides()->sync($rows);
            $this->access->revoke($target);
            $this->audit->record('access.permissions_updated', $actor, $target, $before, $this->access->snapshot($target));
        });
    }
}
