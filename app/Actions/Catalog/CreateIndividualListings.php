<?php

namespace App\Actions\Catalog;

use App\Models\IndividualListing;
use App\Models\ProductFamily;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Atomic orchestration for new independent listing drafts. */
class CreateIndividualListings
{
    public function __construct(private SaveIndividualListing $save) {}

    /** @return array<int, IndividualListing> */
    public function handle(User $actor, ProductFamily $family, array $rows): array
    {
        return DB::transaction(function () use ($actor, $family, $rows): array {
            Gate::forUser($actor->fresh())->authorize('create', IndividualListing::class);
            Gate::forUser($actor)->authorize('price', IndividualListing::class);
            $family = ProductFamily::lockForUpdate()->findOrFail($family->id);
            abort_unless($family->behavior_type === 'direct_sale' && $family->active && ! $family->archived_at, 404);
            $dishIds = array_column($rows, 'dish_id');
            $idempotentTokens = array_column($rows, 'creation_token');
            $existing = IndividualListing::where('family_id', $family->id)
                ->whereIn('dish_id', $dishIds)
                ->whereNotIn('creation_token', $idempotentTokens)
                ->lockForUpdate()
                ->pluck('dish_id')
                ->all();
            if ($existing !== []) {
                $conflicts = collect($rows)->keys()->filter(fn ($index) => in_array($rows[$index]['dish_id'], $existing, true));
                throw ValidationException::withMessages($conflicts->mapWithKeys(fn ($index) => ["listings.{$index}.dish_id" => 'This Food Item is already configured for this service. Edit its existing listing instead.'])->all());
            }

            $created = [];
            foreach ($rows as $index => $row) {
                try {
                    $created[] = $this->save->handle($actor, [
                        ...$row,
                        'family_id' => $family->id,
                        'offering_option_id' => null,
                    ]);
                } catch (ValidationException $exception) {
                    throw ValidationException::withMessages(collect($exception->errors())
                        ->mapWithKeys(fn (array $messages, string $field) => ["listings.{$index}.{$field}" => $messages])
                        ->all());
                }
            }

            return $created;
        }, 3);
    }
}
