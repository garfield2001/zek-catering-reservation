<?php

namespace App\Actions\Catalog;

use App\Models\ProductFamily;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ArchiveCollection
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(User $actor, ProductFamily $family): void
    {
        DB::transaction(function () use ($actor, $family): void {
            $family = ProductFamily::lockForUpdate()->findOrFail($family->id);
            Gate::forUser($actor->fresh())->authorize('manage', ProductFamily::class);
            abort_if($family->archived_at !== null, 404);
            $before = $family->only('name', 'active', 'archived_at');
            $family->forceFill(['active' => false, 'archived_at' => now()])->save();
            $this->audit->record('catalog.collection_archived', $actor, $family, $before,
                $family->only('name', 'active', 'archived_at'));
        });
    }
}
