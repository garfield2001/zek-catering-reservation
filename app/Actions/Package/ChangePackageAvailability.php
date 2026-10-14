<?php

namespace App\Actions\Package;

use App\Models\PackageVersion;
use App\Models\Product;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ChangePackageAvailability
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(User $actor, Product $subject, string $operation): void
    {
        DB::transaction(function () use ($actor, $subject, $operation) {
            Gate::forUser($actor->fresh())->authorize('lifecycle', $subject);
            if (! in_array($operation, ['disable', 'enable', 'archive'], true)) {
                throw ValidationException::withMessages(['operation' => 'Unsupported package availability action.']);
            }
            $subject = Product::lockForUpdate()->findOrFail($subject->id);
            if ($subject->archived_at !== null) {
                throw ValidationException::withMessages(['operation' => 'Archived package offerings cannot be reactivated.']);
            }
            if ($operation === 'enable' && ($subject->family->archived_at !== null || ! $subject->family->active)) {
                throw ValidationException::withMessages(['operation' => 'Enable the parent collection or product first.']);
            }
            $before = $subject->only('active', 'archived_at');
            if ($operation === 'archive') {
                $subject->forceFill(['active' => false, 'archived_at' => now()])->save();
                $draft = PackageVersion::where('package_product_id', $subject->id)->where('status', 'draft')->lockForUpdate()->first();
                if ($draft) {
                    $draft->forceFill(['status' => 'abandoned', 'abandoned_at' => now(), 'abandoned_by' => $actor->id])->save();
                    $this->audit->record('package.draft_abandoned', $actor, $draft, null, ['archived_with' => $subject->id]);
                }
            } else {
                $subject->active = $operation === 'enable';
                $subject->save();
            }
            $this->audit->record('package.'.class_basename($subject).'_'.$operation, $actor, $subject,
                $before, $subject->only('active', 'archived_at'));
        });
    }
}
