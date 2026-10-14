<?php

namespace App\Actions\Package;

use App\Models\PackageVersion;
use App\Models\Product;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AbandonPackageDraft
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(User $actor, PackageVersion $draft, int $revision): void
    {
        DB::transaction(function () use ($actor, $draft, $revision): void {
            $draft = PackageVersion::lockForUpdate()->findOrFail($draft->id);
            Product::lockForUpdate()->findOrFail($draft->package_product_id);
            Gate::forUser($actor->fresh())->authorize('update', $draft);
            if ($draft->status !== 'draft' || $draft->draft_revision !== $revision) {
                throw ValidationException::withMessages(['draft_revision' => 'The draft changed or is no longer editable. Reload before abandoning.']);
            }
            $draft->forceFill(['status' => 'abandoned', 'abandoned_at' => now(), 'abandoned_by' => $actor->id])->save();
            $this->audit->record('package.draft_abandoned', $actor, $draft, null, ['version_number' => $draft->version_number]);
        });
    }
}
