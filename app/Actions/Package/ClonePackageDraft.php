<?php

namespace App\Actions\Package;

use App\Models\PackageVersion;
use App\Models\Product;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Package\PackagePresentation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ClonePackageDraft
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(User $actor, Product $product, PackageVersion $source): PackageVersion
    {
        return DB::transaction(function () use ($actor, $product, $source): PackageVersion {
            $product = Product::lockForUpdate()->findOrFail($product->id);
            Gate::forUser($actor->fresh())->authorize('update', $product);
            Gate::forUser($actor->fresh())->authorize('history', PackageVersion::class);
            $source = PackageVersion::with(['groups.options', 'groups.categoryRules', 'groups.dishRules', 'foodItems', 'requiredCategories'])
                ->where('package_product_id', $product->id)->findOrFail($source->id);
            if ($product->archived_at !== null || $source->status !== 'published') {
                throw ValidationException::withMessages(['version' => 'Only a published package version can start a revision.']);
            }
            if (PackageVersion::where('package_product_id', $product->id)->where('status', 'draft')->exists()) {
                throw ValidationException::withMessages(['version' => 'Resolve the active draft before starting another revision.']);
            }
            $draft = $source->replicate(['status', 'published_snapshot', 'published_at', 'published_by', 'abandoned_at', 'abandoned_by', 'created_at', 'updated_at']);
            $draft->forceFill(['package_product_id' => $product->id, 'version_number' => (int) PackageVersion::where('package_product_id', $product->id)->max('version_number') + 1,
                'status' => 'draft', 'draft_revision' => 1, 'cloned_from_id' => $source->id, 'created_by' => $actor->id])->save();
            foreach ($source->groups as $group) {
                $copy = $group->replicate(['created_at', 'updated_at']);
                $copy->package_version_id = $draft->id;
                $copy->save();
                foreach ($group->options as $option) {
                    $child = $option->replicate(['created_at', 'updated_at']);
                    $child->group_id = $copy->id;
                    $child->save();
                }
                foreach ($group->categoryRules as $rule) {
                    DB::table('group_category_rules')->insert([...$rule->only('category_id', 'minimum_count', 'maximum_count'), 'group_id' => $copy->id, 'created_at' => now(), 'updated_at' => now()]);
                }
                foreach ($group->dishRules as $rule) {
                    DB::table('group_dish_rules')->insert([...$rule->only('dish_id', 'effect'), 'group_id' => $copy->id, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
            $fixedByDish = DB::table('package_version_food_items')
                ->where('package_version_id', $source->id)
                ->pluck('is_fixed', 'dish_id');
            foreach ($source->foodItems as $dish) {
                DB::table('package_version_food_items')->insert(['package_version_id' => $draft->id, 'dish_id' => $dish->id, 'is_fixed' => (bool) $fixedByDish->get($dish->id), 'always_include' => (bool) $dish->pivot->getAttribute('always_include'), 'included_quantity' => $dish->pivot->getAttribute('included_quantity'), 'included_unit' => $dish->pivot->getAttribute('included_unit'), 'price_adjustment_minor' => $dish->pivot->getAttribute('price_adjustment_minor'), 'created_at' => now(), 'updated_at' => now()]);
            }
            foreach ($source->requiredCategories as $category) {
                DB::table('package_version_required_categories')->insert(['package_version_id' => $draft->id, 'category_id' => $category->id, 'created_at' => now(), 'updated_at' => now()]);
            }
            app(PackagePresentation::class)->save($draft, app(PackagePresentation::class)->input($source));
            foreach ($source->sizeGroups()->with('choices')->get() as $group) {
                $copy = $draft->sizeGroups()->create($group->only('dish_id', 'measurement_unit_id'));
                foreach ($group->choices as $choice) {
                    $copy->choices()->create($choice->only('quantity', 'size_label', 'extra_price_minor', 'is_default'));
                }
            }
            $this->audit->record('package.draft_cloned', $actor, $draft, null, ['cloned_from_id' => $source->id, 'version_number' => $draft->version_number]);

            return $draft;
        });
    }
}
