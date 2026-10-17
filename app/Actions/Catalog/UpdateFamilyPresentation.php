<?php

namespace App\Actions\Catalog;

use App\Models\ProductFamily;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class UpdateFamilyPresentation
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(User $actor, ProductFamily $family, array $data): ProductFamily
    {
        return DB::transaction(function () use ($actor, $family, $data) {
            $family = ProductFamily::lockForUpdate()->findOrFail($family->id);
            Gate::forUser($actor->fresh())->authorize('manage', ProductFamily::class);
            abort_if($family->archived_at !== null, 404);
            if (! $family->is_addon && isset($data['name']) && $data['name'] !== $family->name) {
                throw ValidationException::withMessages(['name' => 'Catering Packages is system-managed. You may update its description or availability, but not rename it.']);
            }
            $before = $family->only('name', 'description', 'sort_order', 'active');
            $family->forceFill(collect($data)->only('name', 'description', 'active')->all())->save();
            $this->audit->record('catalog.family_presentation_updated', $actor, $family, $before,
                $family->only('name', 'description', 'sort_order', 'active'));

            return $family;
        });
    }
}
