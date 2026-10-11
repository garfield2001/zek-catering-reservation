<?php

namespace App\Actions\Package;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\VariantVersion;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AbandonVariantDraft
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(User $actor, VariantVersion $draft, int $revision): void
    {
        DB::transaction(function () use ($actor, $draft, $revision) {
            $owner = ProductVariant::findOrFail($draft->variant_id);
            Product::lockForUpdate()->findOrFail($owner->product_id);
            ProductVariant::lockForUpdate()->findOrFail($owner->id);
            $draft = VariantVersion::lockForUpdate()->findOrFail($draft->id);
            Gate::forUser($actor->fresh())->authorize('update', $draft);
            if ($draft->status !== 'draft' || $draft->draft_revision !== $revision) {
                throw ValidationException::withMessages(['draft_revision' => 'The draft changed or is no longer editable. Reload before abandoning.']);
            }
            $draft->forceFill(['status' => 'abandoned', 'abandoned_at' => now(), 'abandoned_by' => $actor->id])->save();
            $this->audit->record('package.draft_abandoned', $actor, $draft, null,
                ['version_number' => $draft->version_number]);
        });
    }
}
