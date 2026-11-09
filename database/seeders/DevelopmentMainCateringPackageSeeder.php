<?php

namespace Database\Seeders;

use App\Actions\Catalog\EnsurePrimaryService;
use App\Actions\Package\PublishPackageVersion;
use App\Actions\Package\SavePackageMenuConfiguration;
use App\Actions\Package\SavePackageVersion;
use App\Actions\Package\SaveProduct;
use App\Models\Dish;
use App\Models\DishCategory;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Usable, local-only Main Catering fixtures. Existing owner changes remain untouched. */
class DevelopmentMainCateringPackageSeeder extends Seeder
{
    /** @var array<string, array{price: string, token: string, selections: int, description: string}> */
    private const PACKAGES = [
        'Package A' => ['price' => '380.00', 'token' => 'fb7d6a71-84cd-4ce0-9800-000000000001', 'selections' => 4, 'description' => 'A practical four-food catering menu for intimate celebrations and office gatherings.'],
        'Package B' => ['price' => '450.00', 'token' => 'fb7d6a71-84cd-4ce0-9800-000000000002', 'selections' => 5, 'description' => 'A balanced five-food catering menu with flexible customer choices for medium events.'],
        'Package C' => ['price' => '480.00', 'token' => 'fb7d6a71-84cd-4ce0-9800-000000000003', 'selections' => 6, 'description' => 'A generous six-food catering menu for celebrations that need more variety.'],
        'Package D' => ['price' => '520.00', 'token' => 'fb7d6a71-84cd-4ce0-9800-000000000004', 'selections' => 7, 'description' => 'Our most varied seven-food catering menu for larger celebrations and corporate events.'],
    ];

    /** @var list<string> */
    private const MENU_CATEGORIES = ['Beef', 'Chicken', 'Pork', 'Desserts', 'Rice', 'Drinks'];

    public function run(): void
    {
        $actor = User::query()
            ->where('active', true)
            ->whereHas('roles', fn ($roles) => $roles->where('roles.id', Role::where('code', 'admin')->value('id')))
            ->orderBy('id')
            ->first();
        if (! $actor) {
            throw new \RuntimeException('Development demo packages require an existing active Administrator. Create one with the approved bootstrap command, then run this seeder again.');
        }
        $family = app(EnsurePrimaryService::class)->handle();
        $categories = DishCategory::query()
            ->whereIn('name', self::MENU_CATEGORIES)
            ->where('active', true)
            ->get()
            ->keyBy('name');
        $missing = array_diff(self::MENU_CATEGORIES, $categories->keys()->all());
        if ($missing !== []) {
            throw new \RuntimeException('Development Main Catering packages require active '.implode(', ', $missing).' Food Categories.');
        }
        $foodIds = Dish::query()
            ->where('active', true)
            ->whereIn('category_id', $categories->pluck('id'))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        if ($foodIds === []) {
            throw new \RuntimeException('Development Main Catering packages require active Food Items.');
        }
        $categoryRules = collect(['Rice', 'Drinks'])->map(fn (string $name): array => [
            'category_id' => $categories->get($name)->id,
            'minimum_count' => 1,
            'maximum_count' => 1,
            'maximum_count_explicit' => true,
        ])->all();
        foreach (self::PACKAGES as $name => $fixture) {
            DB::transaction(function () use ($actor, $family, $name, $fixture, $foodIds, $categoryRules): void {
                $token = $fixture['token'];
                $product = Product::where('creation_token', $token)->lockForUpdate()->first();
                if ($product) {
                    if ($product->family_id !== $family->id) {
                        throw new \RuntimeException("Demo package identity {$token} belongs to a different record.");
                    }
                    if (! $this->isLegacyUnconfiguredFixture($product, $name)) {
                        // The immutable creation token establishes fixture ownership. Preserve any
                        // subsequent Administrator edits, including a completed Draft or publication.
                        return;
                    }
                    $draft = $product->versions()->where('status', 'draft')->sole();
                } else {
                    $product = app(SaveProduct::class)->handle($actor, [
                        'family_id' => $family->id,
                        'name' => $name,
                        'description' => $fixture['description'],
                        'active' => true,
                        'code' => 'demo_main_'.strtolower(str_replace(' ', '_', $name)),
                        'creation_token' => $token,
                    ]);
                    $draft = $product->versions()->where('status', 'draft')->sole();
                }
                app(SavePackageVersion::class)->handle($actor, $product, [
                    'draft_revision' => $draft->draft_revision,
                    'base_price_pesos' => $fixture['price'],
                    'price_basis' => 'per_pax',
                    'selection_policy' => 'uniform',
                    'minimum_quantity' => 150,
                    'quantity_step' => 50,
                    'maximum_quantity' => null,
                ], $draft);
                $draft = $draft->fresh();
                app(SavePackageMenuConfiguration::class)->handle($actor, $draft, [
                    'draft_revision' => $draft->draft_revision,
                    'total_selection_count' => $fixture['selections'],
                    'food_item_ids' => $foodIds,
                    'category_choice_rules' => $categoryRules,
                ]);
                $draft = $draft->fresh();
                app(PublishPackageVersion::class)->handle($actor, $draft, $draft->draft_revision);
            });
        }
    }

    private function isLegacyUnconfiguredFixture(Product $product, string $expectedName): bool
    {
        if ($product->current_version_id !== null || $product->name !== $expectedName || $product->description !== null) {
            return false;
        }
        $versions = $product->versions()->get();

        return $versions->count() === 1
            && $versions->sole()->status === 'draft'
            && $versions->sole()->foodItems()->doesntExist();
    }
}
