<?php

namespace App\Actions\Package;

use App\Models\SelectionGroup;
use App\Models\User;
use App\Models\VariantVersion;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class DeleteSelectionGroup
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(User $actor, SelectionGroup $group, ?int $draftRevision = null): void
    {
        DB::transaction(function () use ($actor, $group, $draftRevision): void {
            $version = VariantVersion::lockForUpdate()->findOrFail($group->version_id);
            abort_if($version->status !== 'draft', 404);
            if ($draftRevision !== null && $draftRevision !== $version->draft_revision) {
                throw ValidationException::withMessages(['draft_revision' => 'This draft changed. Reload and review it before removing the group.']);
            }
            $group = SelectionGroup::with('version')->lockForUpdate()->findOrFail($group->id);
            abort_if($group->version->status !== 'draft', 404);
            Gate::forUser($actor->fresh())->authorize('update', $group);
            $before = [...$group->only('group_code', 'name', 'mode', 'slot_kind', 'minimum_count',
                'maximum_count', 'unique_dishes', 'category_mode', 'inclusion_count', 'sort_order'),
                'options' => DB::table('group_options')->where('group_id', $group->id)->get()->map(fn ($row) => (array) $row)->all(),
                'category_rules' => DB::table('group_category_rules')->where('group_id', $group->id)->get()->map(fn ($row) => (array) $row)->all(),
                'dish_rules' => DB::table('group_dish_rules')->where('group_id', $group->id)->get()->map(fn ($row) => (array) $row)->all()];
            DB::table('group_options')->where('group_id', $group->id)->delete();
            DB::table('group_category_rules')->where('group_id', $group->id)->delete();
            DB::table('group_dish_rules')->where('group_id', $group->id)->delete();
            $group->delete();
            $version->increment('draft_revision');
            $this->audit->record('package.group_deleted', $actor, $group, $before, null);
        });
    }
}
