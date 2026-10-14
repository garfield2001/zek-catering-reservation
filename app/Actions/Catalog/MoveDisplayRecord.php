<?php

namespace App\Actions\Catalog;

use App\Models\DishCategory;
use App\Models\PackageVersion;
use App\Models\Product;
use App\Models\ProductFamily;
use App\Models\Role;
use App\Models\SelectionGroup;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class MoveDisplayRecord
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(User $actor, ProductFamily|DishCategory|Product|SelectionGroup $record,
        string $direction, ?int $draftRevision = null): void
    {
        DB::transaction(function () use ($actor, $record, $direction, $draftRevision) {
            if (! in_array($direction, ['up', 'down'], true)) {
                throw ValidationException::withMessages(['direction' => 'Choose Move Up or Move Down.']);
            }
            $version = null;
            if ($record instanceof ProductFamily || $record instanceof DishCategory) {
                Role::where('code', 'admin')->lockForUpdate()->firstOrFail();
                $query = $record instanceof ProductFamily
                    ? ProductFamily::whereNull('archived_at') : DishCategory::query();
            } elseif ($record instanceof Product) {
                ProductFamily::lockForUpdate()->findOrFail($record->family_id);
                $query = Product::where('family_id', $record->family_id)->whereNull('archived_at');
            } else {
                $version = PackageVersion::lockForUpdate()->findOrFail($record->package_version_id);
                abort_if($version->status !== 'draft', 404);
                if ($draftRevision !== $version->draft_revision) {
                    throw ValidationException::withMessages(['draft_revision' => 'This draft changed. Reload before reordering.']);
                }
                $query = SelectionGroup::where('package_version_id', $record->package_version_id);
            }
            Gate::forUser($actor->fresh())->authorize($record instanceof ProductFamily ? 'manage' : 'update', $record);
            $rows = $query->orderBy('sort_order')->orderBy('id')->lockForUpdate()->get();
            $index = $rows->search(fn (Model $row) => $row->id === $record->id);
            if ($index === false) {
                abort(404);
            }
            $other = $direction === 'up' ? $index - 1 : $index + 1;
            if (! isset($rows[$other])) {
                return;
            }
            if ($rows[$index]->sort_order !== $rows[$other]->sort_order) {
                $previous = $rows[$index]->sort_order;
                $rows[$index]->sort_order = $rows[$other]->sort_order;
                $rows[$other]->sort_order = $previous;
                $rows[$index]->save();
                $rows[$other]->save();
            } else {
                $ordered = $rows->all();
                [$ordered[$index], $ordered[$other]] = [$ordered[$other], $ordered[$index]];
                foreach ($ordered as $position => $row) {
                    $row->sort_order = $position + 1;
                    $row->save();
                }
            }
            if ($version) {
                $version->increment('draft_revision');
            }
            $this->audit->record('catalog.display_order_changed', $actor, $record,
                ['direction' => $direction], ['sort_order' => $record->fresh()->sort_order]);
        });
    }
}
