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

class CloneVariantDraft
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(User $actor, ProductVariant $owner, VariantVersion $source): VariantVersion
    {
        return DB::transaction(function () use ($actor, $owner, $source) {
            Product::lockForUpdate()->findOrFail($owner->product_id);
            $owner = ProductVariant::lockForUpdate()->findOrFail($owner->id);
            Gate::forUser($actor->fresh())->authorize('update', $owner);
            Gate::forUser($actor->fresh())->authorize('history', VariantVersion::class);
            $source = VariantVersion::with(['groups.options', 'groups.categoryRules', 'groups.dishRules'])
                ->where('variant_id', $owner->id)->findOrFail($source->id);
            if ($owner->archived_at !== null || $owner->product->archived_at !== null || $source->status !== 'published') {
                throw ValidationException::withMessages(['version' => 'Only a published version of a current variant can start a revision.']);
            }
            if (VariantVersion::where('variant_id', $owner->id)->where('status', 'draft')->exists()) {
                throw ValidationException::withMessages(['version' => 'Resolve the active draft before starting another revision.']);
            }
            $number = (int) VariantVersion::where('variant_id', $owner->id)->max('version_number') + 1;
            $draft = $source->replicate(['status', 'published_snapshot', 'published_at', 'published_by',
                'abandoned_at', 'abandoned_by', 'created_at', 'updated_at']);
            $draft->forceFill(['version_number' => $number, 'status' => 'draft', 'draft_revision' => 1,
                'cloned_from_id' => $source->id, 'created_by' => $actor->id])->save();
            foreach ($source->groups as $group) {
                $copy = $group->replicate(['created_at', 'updated_at']);
                $copy->version_id = $draft->id;
                $copy->save();
                foreach ($group->options as $option) {
                    $child = $option->replicate(['created_at', 'updated_at']);
                    $child->group_id = $copy->id;
                    $child->save();
                }
                foreach ($group->categoryRules as $rule) {
                    DB::table('group_category_rules')->insert([
                        ...$rule->only('category_id', 'minimum_count', 'maximum_count'),
                        'group_id' => $copy->id, 'created_at' => now(), 'updated_at' => now()]);
                }
                foreach ($group->dishRules as $rule) {
                    DB::table('group_dish_rules')->insert([
                        ...$rule->only('dish_id', 'effect'),
                        'group_id' => $copy->id, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
            $this->audit->record('package.draft_cloned', $actor, $draft, null,
                ['cloned_from_id' => $source->id, 'version_number' => $number]);

            return $draft;
        });
    }
}
