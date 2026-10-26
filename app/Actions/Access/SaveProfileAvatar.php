<?php

namespace App\Actions\Access;

use App\Models\User;
use App\Services\Access\UserAvatars;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class SaveProfileAvatar
{
    public function __construct(private UserAvatars $avatars, private AuditLogger $audit) {}

    public function handle(User $actor, array $data): User
    {
        return DB::transaction(function () use ($actor, $data): User {
            $user = User::lockForUpdate()->findOrFail($actor->id);
            $before = $user->only('avatar_path', 'avatar_thumbnail_path');
            $old = $user->only('avatar_disk', 'avatar_path', 'avatar_thumbnail_path');
            if (($data['avatar'] ?? null) instanceof UploadedFile) {
                $stored = $this->avatars->store($data['avatar']);
                DB::afterRollback(fn () => $this->avatars->delete($stored));
                $user->forceFill($stored)->save();
                DB::afterCommit(fn () => $this->avatars->delete($old));
            } elseif ($data['remove_avatar'] ?? false) {
                $user->forceFill(array_fill_keys(array_keys($old), null))->save();
                DB::afterCommit(fn () => $this->avatars->delete($old));
            }
            $this->audit->record('access.profile_avatar_updated', $user, $user, $before, $user->only('avatar_path', 'avatar_thumbnail_path'));

            return $user->fresh();
        });
    }
}
