<?php

namespace App\Actions\Catalog;

use App\Models\Dish;
use App\Models\DishCategory;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Catalog\FoodImages;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SaveDish
{
    public function __construct(private AuditLogger $audit, private FoodImages $images) {}

    public function handle(User $actor, array $data, ?Dish $dish = null): Dish
    {
        return DB::transaction(function () use ($actor, $data, $dish) {
            Gate::forUser($actor->fresh())->authorize($dish ? 'update' : 'create', $dish ?? Dish::class);
            if (! empty($data['new_category_name'])) {
                $created = app(SaveCategory::class)->handle($actor, ['name' => $data['new_category_name'], 'active' => true]);
                $data['category_id'] = $created->id;
            }
            unset($data['new_category_name']);
            $category = DishCategory::lockForUpdate()->findOrFail($data['category_id']);
            if ($data['active'] && ! $category->active) {
                throw ValidationException::withMessages(['category_id' => 'Activate the category before making this food item available.']);
            }
            $dish = $dish ? Dish::lockForUpdate()->findOrFail($dish->id) : new Dish;
            Gate::forUser($actor->fresh())->authorize($dish->exists ? 'update' : 'create', $dish->exists ? $dish : Dish::class);
            $before = $dish->exists ? $this->snapshot($dish) : null;
            $oldImage = $dish->only('image_disk', 'image_path', 'image_thumbnail_path', 'image_width', 'image_height');
            if (($data['image'] ?? null) instanceof UploadedFile) {
                $image = $this->images->store($data['image']);
                // Register with every open ancestor: an inner savepoint may succeed
                // while its enclosing workflow later rolls back.
                foreach (app('db.transactions')->getPendingTransactions() as $transaction) {
                    if ($transaction->connection === DB::connection()->getName()) {
                        $transaction->addCallbackForRollback(fn () => $this->images->delete($image));
                    }
                }
                $dish->forceFill($image);
                DB::afterCommit(fn () => $this->images->delete($oldImage));
            } elseif ($data['remove_image'] ?? false) {
                $dish->forceFill(array_fill_keys(array_keys($oldImage), null));
                DB::afterCommit(fn () => $this->images->delete($oldImage));
            }
            $dish->fill(collect($data)->except(['family_ids', 'code', 'image', 'remove_image'])->all());
            if (! $dish->exists) {
                $dish->code = $data['code'] ?? 'item_'.strtolower((string) Str::ulid());
            }
            $dish->save();
            $this->audit->record($before ? 'catalog.dish_updated' : 'catalog.dish_created', $actor, $dish, $before, $this->snapshot($dish));

            return $dish;
        });
    }

    private function snapshot(Dish $dish): array
    {
        return $dish->only('code', 'name', 'description', 'category_id', 'active', 'image_path', 'image_width', 'image_height');
    }
}
