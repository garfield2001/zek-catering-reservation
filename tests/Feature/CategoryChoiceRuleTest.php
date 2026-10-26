<?php

namespace Tests\Feature;

use App\Actions\Package\ClonePackageDraft;
use App\Actions\Package\PublishPackageVersion;
use App\Actions\Package\SavePackageMenuConfiguration;
use App\Actions\Package\SavePackageVersion;
use App\Actions\Package\SaveProduct;
use App\Models\Dish;
use App\Models\DishCategory;
use App\Models\ProductFamily;
use App\Models\Role;
use App\Models\User;
use App\Services\Package\PackageMenuConfiguration;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\LegacyCollections;
use Tests\TestCase;

class CategoryChoiceRuleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private ProductFamily $family;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        LegacyCollections::seed();
        $this->admin = User::factory()->create();
        $this->admin->roles()->attach(Role::where('code', 'admin')->sole());
        $this->family = ProductFamily::where('code', 'main_catering')->sole();
    }

    public function test_explicit_customer_choice_range_persists_publishes_and_enforces_its_minimum_and_maximum(): void
    {
        $rice = DishCategory::create(['code' => 'choice_rice', 'name' => 'Rice', 'active' => true]);
        $chicken = DishCategory::create(['code' => 'choice_chicken', 'name' => 'Chicken', 'active' => true]);
        $bread = DishCategory::create(['code' => 'choice_bread', 'name' => 'Bread', 'active' => true]);
        $riceFoods = collect(['Plain Rice', 'Fried Rice', 'Steamed Rice'])->map(fn (string $name, int $index) => Dish::create([
            'code' => 'choice_rice_'.$index,
            'name' => $name,
            'category_id' => $rice->id,
            'active' => true,
        ]));
        $chickenFoods = collect(['Chicken Adobo', 'Chicken BBQ'])->map(fn (string $name, int $index) => Dish::create([
            'code' => 'choice_chicken_'.$index,
            'name' => $name,
            'category_id' => $chicken->id,
            'active' => true,
        ]));
        $included = Dish::create(['code' => 'choice_garlic_bread', 'name' => 'Garlic Bread', 'category_id' => $bread->id, 'active' => true]);
        $product = app(SaveProduct::class)->handle($this->admin, [
            'family_id' => $this->family->id,
            'name' => 'Choice Rules Package',
            'description' => null,
            'active' => true,
            'creation_token' => (string) str()->uuid(),
        ]);
        $draft = $product->versions()->sole();
        app(SavePackageVersion::class)->handle($this->admin, $product, [
            'base_price_pesos' => '750.00',
            'selection_policy' => 'uniform',
            'minimum_quantity' => 150,
            'quantity_step' => 1,
            'maximum_quantity' => null,
            'total_selection_count' => 3,
            'draft_revision' => $draft->draft_revision,
        ], $draft);
        $draft->refresh();
        app(SavePackageMenuConfiguration::class)->handle($this->admin, $draft, [
            'draft_revision' => $draft->draft_revision,
            'total_selection_count' => 3,
            'food_item_ids' => [
                $included->id,
                ...$riceFoods->pluck('id')->all(),
                ...$chickenFoods->pluck('id')->all(),
            ],
            'food_details' => [$included->id => ['is_fixed' => true]],
            'category_choice_rules' => [[
                'category_id' => $rice->id,
                'minimum_count' => 1,
                'maximum_count' => 1,
                'maximum_count_explicit' => true,
            ]],
        ]);
        $draft->refresh()->load('requiredCategories');
        $rule = $draft->requiredCategories->sole();
        $this->assertSame(1, (int) $rule->pivot->minimum_count);
        $this->assertSame(1, (int) $rule->pivot->maximum_count);
        $this->assertTrue((bool) $rule->pivot->maximum_count_explicit);

        $valid = [$included->id, $riceFoods[0]->id, $chickenFoods[0]->id];
        $this->assertEqualsCanonicalizing($valid, app(PackageMenuConfiguration::class)->validateCustomerSelection($draft, $valid));
        $this->assertInvalidSelection($draft, [$included->id, $chickenFoods[0]->id, $chickenFoods[1]->id]);
        $this->assertInvalidSelection($draft, [$included->id, $riceFoods[0]->id, $riceFoods[1]->id]);

        $published = app(PublishPackageVersion::class)->handle($this->admin, $draft, $draft->draft_revision);
        $this->assertSame(1, $published->snapshot()['menu']['category_choice_rules'][0]['minimum_count']);
        $this->assertSame(1, $published->snapshot()['menu']['category_choice_rules'][0]['maximum_count']);
        $this->assertTrue($published->snapshot()['menu']['category_choice_rules'][0]['maximum_count_explicit']);
        $this->assertEqualsCanonicalizing($valid, app(PackageMenuConfiguration::class)->validateCustomerSelection($published, $valid));

        $clone = app(ClonePackageDraft::class)->handle($this->admin, $product->fresh(), $published->fresh());
        $this->assertSame(1, (int) $clone->requiredCategories()->sole()->pivot->minimum_count);
        $this->assertSame(1, (int) $clone->requiredCategories()->sole()->pivot->maximum_count);
    }

    public function test_a_single_allowed_food_in_an_explicit_customer_choice_category_becomes_an_inclusion(): void
    {
        $rice = DishCategory::create(['code' => 'single_choice_rice', 'name' => 'Rice', 'active' => true]);
        $other = DishCategory::create(['code' => 'single_choice_other', 'name' => 'Other', 'active' => true]);
        $friedRice = Dish::create(['code' => 'single_choice_fried_rice', 'name' => 'Fried Rice', 'category_id' => $rice->id, 'active' => true]);
        $pasta = Dish::create(['code' => 'single_choice_pasta', 'name' => 'Pasta', 'category_id' => $other->id, 'active' => true]);
        $product = app(SaveProduct::class)->handle($this->admin, ['family_id' => $this->family->id, 'name' => 'Single Choice Package', 'description' => null, 'active' => true, 'creation_token' => (string) str()->uuid()]);
        $draft = $product->versions()->sole();
        app(SavePackageVersion::class)->handle($this->admin, $product, ['base_price_pesos' => '750.00', 'selection_policy' => 'uniform', 'minimum_quantity' => 150, 'quantity_step' => 1, 'maximum_quantity' => null, 'total_selection_count' => 2, 'draft_revision' => $draft->draft_revision], $draft);
        $draft->refresh();

        app(SavePackageMenuConfiguration::class)->handle($this->admin, $draft, [
            'draft_revision' => $draft->draft_revision,
            'total_selection_count' => 2,
            'food_item_ids' => [$friedRice->id, $pasta->id],
            'food_details' => [],
            'category_choice_rules' => [['category_id' => $rice->id, 'minimum_count' => 1, 'maximum_count' => 1, 'maximum_count_explicit' => true]],
        ]);

        $this->assertTrue((bool) $draft->fresh()->foodItems()->whereKey($friedRice)->sole()->pivot->is_fixed);
    }

    private function assertInvalidSelection($version, array $selection): void
    {
        try {
            app(PackageMenuConfiguration::class)->validateCustomerSelection($version->fresh(), $selection);
            $this->fail('Expected the category customer-choice rule to reject the selection.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }
    }
}
