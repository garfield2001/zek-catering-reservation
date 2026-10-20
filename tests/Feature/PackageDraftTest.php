<?php

namespace Tests\Feature;

use App\Actions\Package\SavePackageVersion;
use App\Actions\Package\SaveProduct;
use App\Actions\Package\SaveSelectionGroup;
use App\Models\AuditEvent;
use App\Models\Dish;
use App\Models\DishCategory;
use App\Models\DishOffering;
use App\Models\OfferingOption;
use App\Models\PackageVersion;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductFamily;
use App\Models\Role;
use App\Models\User;
use App\Services\Catalog\PackagePricePreview;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Support\LegacyCollections;
use Tests\Support\ServiceConfiguration;
use Tests\TestCase;

class PackageDraftTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        LegacyCollections::seed();
    }

    private function actor(string $role = 'admin'): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('code', $role)->sole());

        return $user;
    }

    private function signIn(User $user): static
    {
        return $this->actingAs($user)->withSession(['access_version' => $user->access_version]);
    }

    private function grant(User $user, string $code): void
    {
        $user->permissionOverrides()->syncWithoutDetaching([
            Permission::where('code', $code)->sole()->id => ['effect' => 'allow'],
        ]);
        $user->unsetRelations();
    }

    private function family(string $code): ProductFamily
    {
        return ProductFamily::where('code', $code)->sole();
    }

    private function option(ProductFamily $family, string $unit = 'tray', ?DishCategory $category = null): OfferingOption
    {
        $code = 'item_'.++$this->sequence;
        $category ??= DishCategory::firstOrCreate(['code' => 'general'], [
            'name' => 'General', 'sort_order' => 0, 'active' => true,
        ]);
        $dish = Dish::create(['code' => $code, 'category_id' => $category->id, 'name' => $code, 'active' => true]);
        $offering = DishOffering::create(['dish_id' => $dish->id, 'family_id' => $family->id,
            'code' => 'standard', 'name' => 'Standard', 'active' => true]);

        return OfferingOption::create(['offering_id' => $offering->id, 'option_code' => 'regular',
            'label' => 'Regular', 'unit' => $unit, 'quantity' => '1.000', 'active' => true]);
    }

    private function draft(User $actor, string $familyCode, array $changes = []): array
    {
        $family = $this->family($familyCode);
        $data = ['code' => 'standard', 'name' => 'Standard', 'active' => true,
            'base_price_minor' => '10000', 'selection_policy' => 'uniform',
            'minimum_quantity' => $family->orderDefault->minimum_quantity, 'quantity_step' => 1,
            'maximum_quantity' => null, ...$changes];
        $initial = app(SaveProduct::class)->handle($actor, ['family_id' => $family->id, 'name' => 'Configured '.++$this->sequence, 'active' => true]);
        $product = $initial;
        $draft = $product->versions()->sole();
        $version = app(SavePackageVersion::class)->handle($actor, $product, [...$data, 'draft_revision' => $draft->draft_revision], $draft);

        return [$product, $version];
    }

    private function group(User $actor, PackageVersion $version, string $kind, string $mode,
        array $options, int $minimum = 1, int $maximum = 1, array $changes = []): void
    {
        $code = $kind.'_'.++$this->sequence;
        app(SaveSelectionGroup::class)->handle($actor, $version, [
            'group_code' => $code, 'name' => $code, 'mode' => $mode, 'slot_kind' => $kind,
            'minimum_count' => $minimum, 'maximum_count' => $maximum, 'unique_dishes' => true,
            'category_mode' => 'free', 'inclusion_count' => '1.000', 'sort_order' => 0,
            'options' => array_map(fn ($option) => ['offering_option_id' => $option->id,
                'is_default' => $mode === 'fixed', 'price_adjustment_minor' => '0'], $options),
            'category_rules' => [], 'dish_rules' => [], ...$changes,
        ]);
    }

    private function selections(PackageVersion $version, array $changes = []): array
    {
        $selected = [];
        foreach ($version->fresh()->groups as $group) {
            $selected[$group->group_code] = $group->options->take($group->minimum_count)->pluck('offering_option_id')->all();
        }

        return [...$selected, ...$changes];
    }

    private function preview(PackageVersion $version, int $quantity, array $allocations): array
    {
        return app(PackagePricePreview::class)->calculate($version->fresh(), $quantity, $allocations);
    }

    private function expectInvalid(callable $operation, string $field): void
    {
        try {
            $operation();
            $this->fail('Invalid configuration or selection was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }

    public function test_addon_package_calculates_fixed_items_and_distinct_dishes_in_integer_minor_units(): void
    {
        $admin = $this->actor();
        [, $version] = $this->draft($admin, 'belly_lechon', ['base_price_minor' => '12555']);
        $family = $version->family;
        $this->group($admin, $version, 'rice', 'fixed', [$this->option($family, 'portion')]);
        $drink = $this->option($family, 'bottle');
        $upgrade = $this->option($family, 'bottle');
        $this->group($admin, $version, 'drink', 'select_one', [$drink, $upgrade], 1, 1, [
            'options' => [
                ['offering_option_id' => $drink->id, 'is_default' => true, 'price_adjustment_minor' => '0'],
                ['offering_option_id' => $upgrade->id, 'is_default' => false, 'price_adjustment_minor' => '275'],
            ],
        ]);
        $first = $this->option($family);
        $second = $this->option($family);
        $third = $this->option($family);
        $this->group($admin, $version, 'dish', 'select_many', [$first, $second, $third], 3, 3);
        $selections = $this->selections($version);
        $selections[array_key_first(array_filter($version->fresh()->groups->keyBy('group_code')->all(),
            fn ($group) => $group->slot_kind === 'drink'))] = [$upgrade->id];
        $result = $this->preview($version, 150, [['quantity' => 150, 'selections' => $selections]]);
        $this->assertSame('1924500', $result['total_minor']);
        $this->assertSame('per_set', $result['price_basis']);
        $this->assertSame('draft_preview', $result['status']);
        $this->assertSame('41250', $result['groups'][0]['adjustments'][0]['line_amount_minor']);
        $this->assertDatabaseHas('audit_events', ['action' => 'package.draft_updated', 'subject_id' => $version->id]);
        $this->assertSame(3, AuditEvent::where('action', 'package.group_created')->count());
    }

    public function test_belly_lechon_selects_kilograms_and_trays_without_a_pax_conversion(): void
    {
        $admin = $this->actor();
        [, $version] = $this->draft($admin, 'belly_lechon', ['base_price_minor' => '500000']);
        $family = $version->family;
        $small = $this->option($family, 'kg');
        $large = $this->option($family, 'kg');
        $this->group($admin, $version, 'lechon', 'select_one', [$small, $large], 1, 1, [
            'options' => [
                ['offering_option_id' => $small->id, 'is_default' => true, 'price_adjustment_minor' => '0'],
                ['offering_option_id' => $large->id, 'is_default' => false, 'price_adjustment_minor' => '100000'],
            ],
        ]);
        $tray = $this->option($family);
        $secondTray = $this->option($family);
        $this->group($admin, $version, 'tray', 'select_many', [$tray, $secondTray], 2, 2);
        $selections = $this->selections($version);
        foreach ($version->fresh()->groups as $group) {
            if ($group->slot_kind === 'lechon') {
                $selections[$group->group_code] = [$large->id];
            }
        }
        $result = $this->preview($version, 2, [['quantity' => 2, 'selections' => $selections]]);
        $this->assertSame('1200000', $result['total_minor']);
        $this->assertSame('per_set', $result['price_basis']);
    }

    public function test_food_pack_split_groups_merge_identical_selections_and_apply_minimum_to_total(): void
    {
        $admin = $this->actor();
        [, $version] = $this->draft($admin, 'food_pack', ['selection_policy' => 'split',
            'minimum_quantity' => 10, 'quantity_step' => 2, 'base_price_minor' => '12500']);
        $family = $version->family;
        $this->group($admin, $version, 'rice', 'fixed', [$this->option($family, 'portion')]);
        $drink = $this->option($family, 'bottle');
        $this->group($admin, $version, 'drink', 'select_one', [$drink], 1, 1, [
            'options' => [['offering_option_id' => $drink->id, 'is_default' => true, 'price_adjustment_minor' => '2500']],
        ]);
        $dish = $this->option($family, 'portion');
        $this->group($admin, $version, 'dish', 'select_many', [$dish]);
        $selected = $this->selections($version);
        $result = $this->preview($version, 10, [
            ['quantity' => 4, 'selections' => $selected], ['quantity' => 6, 'selections' => $selected],
        ]);
        $this->assertSame('150000', $result['total_minor']);
        $this->assertCount(1, $result['groups']);
        $this->assertSame(10, $result['groups'][0]['quantity']);
        try {
            $this->preview($version, 9, [['quantity' => 9, 'selections' => $selected]]);
            $this->fail('Quantity below variant minimum was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('quantity', $exception->errors());
        }
    }

    public function test_pica_pica_fixed_bundle_has_no_premium(): void
    {
        $admin = $this->actor();
        [, $version] = $this->draft($admin, 'pica_pica', ['base_price_minor' => '49900']);
        $this->group($admin, $version, 'bundle_item', 'fixed', [$this->option($version->family, 'piece')]);
        $result = $this->preview($version, 3, [['quantity' => 3, 'selections' => $this->selections($version)]]);
        $this->assertSame('149700', $result['total_minor']);
        $this->assertSame([], $result['groups'][0]['adjustments']);
        $this->expectException(ValidationException::class);
        $this->group($admin, $version, 'bundle_item', 'fixed', [$this->option($version->family, 'piece')], 1, 1, [
            'options' => [['offering_option_id' => $this->option($version->family, 'piece')->id,
                'is_default' => true, 'price_adjustment_minor' => '1']],
        ]);
    }

    public function test_addon_category_quotas_distinct_same_category_dishes_and_duplicate_rejection(): void
    {
        $admin = $this->actor();
        [, $version] = $this->draft($admin, 'belly_lechon');
        $family = $version->family;
        $mains = DishCategory::create(['code' => 'mains', 'name' => 'Mains', 'active' => true]);
        $sides = DishCategory::create(['code' => 'sides', 'name' => 'Sides', 'active' => true]);
        $this->group($admin, $version, 'rice', 'fixed', [$this->option($family, 'portion')]);
        $this->group($admin, $version, 'drink', 'select_one', [$this->option($family, 'bottle')], 1, 1, [
            'options' => [['offering_option_id' => $this->option($family, 'bottle')->id,
                'is_default' => true, 'price_adjustment_minor' => '0']],
        ]);
        $first = $this->option($family, 'tray', $mains);
        $second = $this->option($family, 'tray', $mains);
        $third = $this->option($family, 'tray', $sides);
        $this->group($admin, $version, 'dish', 'select_many', [$first, $second, $third], 3, 3);
        $selected = $this->selections($version);
        $this->assertSame('1500000', $this->preview($version, 150, [
            ['quantity' => 150, 'selections' => $selected],
        ])['total_minor']);
        $dishGroup = $version->fresh()->groups->firstWhere('slot_kind', 'dish');
        $this->expectInvalid(fn () => $this->preview($version, 150, [
            ['quantity' => 150, 'selections' => [...$selected, $dishGroup->group_code => [$first->id, $first->id, $third->id]]],
        ]), 'groups.0.selections.'.$dishGroup->group_code);
        DB::table('group_category_rules')->insert([
            ['group_id' => $dishGroup->id, 'category_id' => $mains->id, 'minimum_count' => 2,
                'maximum_count' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['group_id' => $dishGroup->id, 'category_id' => $sides->id, 'minimum_count' => 1,
                'maximum_count' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);
        $dishGroup->update(['category_mode' => 'restricted']);
        $this->assertSame('1500000', $this->preview($version, 150, [
            ['quantity' => 150, 'selections' => $selected],
        ])['total_minor']);
        $this->expectInvalid(fn () => $this->preview($version, 150, [
            ['quantity' => 150, 'selections' => [...$selected, $dishGroup->group_code => [$first->id, $second->id]]],
        ]), 'groups.0.selections.'.$dishGroup->group_code);
    }

    public function test_fixed_lechon_and_pica_inclusions_cannot_be_substituted(): void
    {
        $admin = $this->actor();
        [, $belly] = $this->draft($admin, 'belly_lechon');
        $lechon = $this->option($belly->family, 'kg');
        $tray = $this->option($belly->family, 'tray');
        $this->group($admin, $belly, 'lechon', 'fixed', [$lechon]);
        $this->group($admin, $belly, 'tray', 'select_many', [$tray]);
        $fixed = $belly->fresh()->groups->firstWhere('slot_kind', 'lechon');
        $selected = $this->selections($belly);
        $this->expectInvalid(fn () => $this->preview($belly, 1, [[
            'quantity' => 1, 'selections' => [...$selected, $fixed->group_code => [$tray->id]],
        ]]), 'groups.0.selections.'.$fixed->group_code);
        [, $pica] = $this->draft($admin, 'pica_pica');
        $item = $this->option($pica->family, 'piece');
        $this->group($admin, $pica, 'bundle_item', 'fixed', [$item], 1, 1, ['inclusion_count' => '4.000']);
        $this->expectInvalid(fn () => $this->preview($pica, 1, [[
            'quantity' => 1, 'selections' => [$pica->fresh()->groups()->sole()->group_code => [$tray->id]],
        ]]), 'groups.0.selections.'.$pica->fresh()->groups()->sole()->group_code);
    }

    public function test_food_pack_split_prices_differ_by_group_and_group_totals_must_match(): void
    {
        $admin = $this->actor();
        [, $version] = $this->draft($admin, 'food_pack', ['selection_policy' => 'split',
            'base_price_minor' => '10000']);
        $family = $version->family;
        $this->group($admin, $version, 'rice', 'fixed', [$this->option($family, 'portion')]);
        $water = $this->option($family, 'bottle');
        $coke = $this->option($family, 'bottle');
        $this->group($admin, $version, 'drink', 'select_one', [$water, $coke], 1, 1, [
            'options' => [
                ['offering_option_id' => $water->id, 'is_default' => true, 'price_adjustment_minor' => '0'],
                ['offering_option_id' => $coke->id, 'is_default' => false, 'price_adjustment_minor' => '2000'],
            ],
        ]);
        $dishA = $this->option($family, 'portion');
        $dishB = $this->option($family, 'portion');
        $this->group($admin, $version, 'dish', 'select_many', [$dishA, $dishB], 2, 2);
        $selected = $this->selections($version);
        $drinkCode = $version->fresh()->groups->firstWhere('slot_kind', 'drink')->group_code;
        $groups = [
            ['quantity' => 4, 'selections' => $selected],
            ['quantity' => 6, 'selections' => [...$selected, $drinkCode => [$coke->id]]],
        ];
        $result = $this->preview($version, 10, $groups);
        $this->assertSame('112000', $result['total_minor']);
        $this->assertCount(2, $result['groups']);
        $this->expectInvalid(fn () => $this->preview($version, 11, $groups), 'groups');
        $this->expectInvalid(fn () => $this->preview($version, 9, [[
            'quantity' => 9, 'selections' => $selected,
        ]]), 'quantity');
        $this->expectInvalid(fn () => $this->preview($version, 10, [[
            'quantity' => 10, 'selections' => [...$selected, $drinkCode => [999999]],
        ]]), 'groups.0.selections.'.$drinkCode);
    }

    public function test_current_availability_fails_closed_at_every_catalog_level(): void
    {
        $admin = $this->actor();
        [, $version] = $this->draft($admin, 'pica_pica');
        $option = $this->option($version->family, 'piece');
        $this->group($admin, $version, 'bundle_item', 'fixed', [$option]);
        $selection = [['quantity' => 1, 'selections' => $this->selections($version)]];
        $this->assertSame('10000', $this->preview($version, 1, $selection)['total_minor']);
        $records = [$option, $option->offering, $option->offering->dish,
            $option->offering->dish->category, $option->offering->family];
        foreach ($records as $record) {
            $record->forceFill(['active' => false])->save();
            $field = $record instanceof ProductFamily ? 'package' : 'groups.0.selections.'.$version->fresh()->groups()->sole()->group_code;
            $this->expectInvalid(fn () => $this->preview($version, 1, $selection), $field);
            $record->forceFill(['active' => true])->save();
        }
        $option->offering->dish->update(['active' => false]);
        $this->expectInvalid(fn () => $this->preview($version, 1, $selection), 'groups.0.selections.'.$version->fresh()->groups()->sole()->group_code);
    }

    public function test_draft_slot_can_be_removed_with_audit_and_staff_cannot_bypass_authorization(): void
    {
        $admin = $this->actor();
        $staff = $this->actor('staff');
        [$product, $version] = $this->draft($admin, 'pica_pica');
        $this->group($admin, $version, 'bundle_item', 'fixed', [$this->option($version->family, 'piece')]);
        $group = $version->fresh()->groups()->sole();
        $path = route('packages.groups.destroy', [$product, $version, $group]);
        $this->signIn($staff)->delete($path)->assertForbidden();
        $this->assertDatabaseHas('selection_groups', ['id' => $group->id]);
        $this->signIn($admin)->delete($path, ['draft_revision' => $version->fresh()->draft_revision])->assertRedirect();
        $this->assertDatabaseMissing('selection_groups', ['id' => $group->id]);
        $this->assertDatabaseMissing('group_options', ['group_id' => $group->id]);
        $this->assertDatabaseHas('audit_events', ['action' => 'package.group_deleted',
            'subject_type' => 'selection_group', 'subject_id' => $group->id]);
    }

    public function test_cross_slot_canonical_dish_duplicates_are_rejected_even_with_different_offerings(): void
    {
        $admin = $this->actor();
        [, $version] = $this->draft($admin, 'belly_lechon');
        $family = $version->family;
        $this->group($admin, $version, 'rice', 'fixed', [$this->option($family, 'portion')]);
        $drink = $this->option($family, 'bottle');
        $this->group($admin, $version, 'drink', 'select_one', [$drink], 1, 1, [
            'options' => [['offering_option_id' => $drink->id, 'is_default' => true, 'price_adjustment_minor' => '0']],
        ]);
        $dish = $this->option($family);
        $alternative = OfferingOption::create(['offering_id' => $dish->offering_id,
            'option_code' => 'large', 'label' => 'Large', 'unit' => 'tray', 'quantity' => '2.000', 'active' => true]);
        $different = $this->option($family);
        $this->group($admin, $version, 'dish', 'select_many', [$dish]);
        $this->group($admin, $version, 'dish', 'select_many', [$alternative, $different]);
        $groups = $version->fresh()->groups->where('slot_kind', 'dish')->values();
        $selected = $this->selections($version);
        $selected[$groups[1]->group_code] = [$alternative->id];
        $this->expectInvalid(fn () => $this->preview($version, 150, [[
            'quantity' => 150, 'selections' => $selected,
        ]]), 'groups.0.selections');
        $selected[$groups[1]->group_code] = [$different->id];
        $this->assertSame('1500000', $this->preview($version, 150, [[
            'quantity' => 150, 'selections' => $selected,
        ]])['total_minor']);
    }

    public function test_food_pack_defaults_to_uniform_and_ten_packs(): void
    {
        $admin = $this->actor();
        [, $version] = $this->draft($admin, 'food_pack');
        $family = $version->family;
        $this->group($admin, $version, 'rice', 'fixed', [$this->option($family, 'portion')]);
        $drink = $this->option($family, 'bottle');
        $this->group($admin, $version, 'drink', 'select_one', [$drink], 1, 1, [
            'options' => [['offering_option_id' => $drink->id, 'is_default' => true, 'price_adjustment_minor' => '0']],
        ]);
        $this->group($admin, $version, 'dish', 'select_many', [$this->option($family, 'portion')]);
        $this->assertSame('uniform', $version->selection_policy);
        $this->assertSame('100000', $this->preview($version, 10, [[
            'quantity' => 10, 'selections' => $this->selections($version),
        ]])['total_minor']);
        $this->expectInvalid(fn () => $this->preview($version, 10, [
            ['quantity' => 5, 'selections' => $this->selections($version)],
            ['quantity' => 5, 'selections' => $this->selections($version)],
        ]), 'groups');
    }

    public function test_postgresql_unique_group_and_option_constraints_hold(): void
    {
        $admin = $this->actor();
        [, $version] = $this->draft($admin, 'pica_pica');
        $option = $this->option($version->family, 'piece');
        $this->group($admin, $version, 'bundle_item', 'fixed', [$option]);
        $group = $version->fresh()->groups()->sole();
        foreach ([
            fn () => DB::table('selection_groups')->insert([
                'package_version_id' => $version->id, 'group_code' => $group->group_code,
                'name' => 'Duplicate', 'mode' => 'fixed', 'slot_kind' => 'bundle_item',
                'minimum_count' => 1, 'maximum_count' => 1, 'inclusion_count' => '1.000',
            ]),
            fn () => DB::table('group_options')->insert([
                'group_id' => $group->id, 'offering_option_id' => $option->id,
                'price_adjustment_minor' => 0,
            ]),
        ] as $write) {
            DB::statement('SAVEPOINT package_unique_test');
            try {
                $write();
                $this->fail('PostgreSQL accepted duplicate package configuration.');
            } catch (QueryException $exception) {
                $this->assertSame('23505', $exception->errorInfo[0]);
            } finally {
                DB::statement('ROLLBACK TO SAVEPOINT package_unique_test');
            }
        }
    }

    public function test_nested_premium_change_requires_price_permission_and_audits_both_values(): void
    {
        $admin = $this->actor();
        $staff = $this->actor('staff');
        [$product, $version] = $this->draft($admin, 'belly_lechon');
        $option = $this->option($version->family, 'bottle');
        $this->group($admin, $version, 'drink', 'select_one', [$option], 1, 1, [
            'options' => [['offering_option_id' => $option->id, 'is_default' => true, 'price_adjustment_minor' => '0']],
        ]);
        $group = $version->fresh()->groups()->sole();
        $payload = ['draft_revision' => $version->fresh()->draft_revision,
            'group_code' => $group->group_code, 'name' => $group->name, 'mode' => 'select_one',
            'slot_kind' => 'drink', 'minimum_count' => 1, 'maximum_count' => 1,
            'unique_dishes' => true, 'category_mode' => 'free', 'inclusion_count' => '1.000',
            'sort_order' => 0, 'options' => [['offering_option_id' => $option->id,
                'is_default' => true, 'price_adjustment_minor' => '275']],
            'category_rules' => [], 'dish_rules' => []];
        $path = route('packages.groups.update', [$product, $version, $group]);
        $this->grant($staff, 'package.manage');
        $this->signIn($staff)->put($path, $payload)->assertForbidden();
        $this->assertDatabaseHas('group_options', ['group_id' => $group->id, 'price_adjustment_minor' => 0]);
        $this->signIn($admin)->put($path, $payload)->assertRedirect();
        $audit = AuditEvent::where('action', 'package.group_updated')->sole();
        $this->assertSame(0, (int) $audit->before_data['options'][0]['price_adjustment_minor']);
        $this->assertSame(275, (int) $audit->after_data['options'][$option->id]['price_adjustment_minor']);
    }

    public function test_dish_allow_and_deny_rules_constrain_selected_options(): void
    {
        $admin = $this->actor();
        [, $version] = $this->draft($admin, 'belly_lechon');
        $family = $version->family;
        $this->group($admin, $version, 'rice', 'fixed', [$this->option($family, 'portion')]);
        $drink = $this->option($family, 'bottle');
        $this->group($admin, $version, 'drink', 'select_one', [$drink], 1, 1, [
            'options' => [['offering_option_id' => $drink->id, 'is_default' => true, 'price_adjustment_minor' => '0']],
        ]);
        $allowed = $this->option($family);
        $denied = $this->option($family);
        $this->group($admin, $version, 'dish', 'select_many', [$allowed, $denied], 1, 1, [
            'dish_rules' => [
                ['dish_id' => $allowed->offering->dish_id, 'effect' => 'allow'],
                ['dish_id' => $denied->offering->dish_id, 'effect' => 'deny'],
            ],
        ]);
        $selected = $this->selections($version);
        $dishCode = $version->fresh()->groups->firstWhere('slot_kind', 'dish')->group_code;
        $this->assertSame('1500000', $this->preview($version, 150, [[
            'quantity' => 150, 'selections' => $selected,
        ]])['total_minor']);
        $this->expectInvalid(fn () => $this->preview($version, 150, [[
            'quantity' => 150, 'selections' => [...$selected, $dishCode => [$denied->id]],
        ]]), 'groups.0.selections.'.$dishCode);
    }

    public function test_renamed_packed_meals_keep_quantity_pricing_and_selection_rules(): void
    {
        $admin = $this->actor();
        $family = $this->family('food_pack');
        $family->forceFill(['name' => 'Packed Meals'])->save();
        [, $version] = $this->draft($admin, 'food_pack', ['base_price_minor' => '12999']);
        $this->group($admin, $version, 'rice', 'fixed', [$this->option($family, 'portion')]);
        $drink = $this->option($family, 'bottle');
        $this->group($admin, $version, 'drink', 'select_one', [$drink], 1, 1, [
            'options' => [['offering_option_id' => $drink->id, 'is_default' => true, 'price_adjustment_minor' => '0']],
        ]);
        $this->group($admin, $version, 'dish', 'select_many', [$this->option($family, 'portion')]);
        $selections = $this->selections($version);
        $this->assertSame('129990', $this->preview($version, 10, [['quantity' => 10, 'selections' => $selections]])['total_minor']);
        try {
            $this->preview($version, 9, [['quantity' => 9, 'selections' => $selections]]);
            $this->fail('Below-minimum quantity was accepted.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('Packed Meals', $exception->errors()['quantity'][0]);
            $this->assertStringContainsString('10 packs', $exception->errors()['quantity'][0]);
            $this->assertStringNotContainsString('Food Pack Lunch', $exception->errors()['quantity'][0]);
        }
        try {
            $this->preview($version, 10, [['quantity' => 5, 'selections' => $selections],
                ['quantity' => 5, 'selections' => $selections]]);
            $this->fail('Uniform variant accepted multiple allocations.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('Packed Meals', $exception->errors()['groups'][0]);
        }
        $family->forceFill(['active' => false])->save();
        $this->expectInvalid(fn () => $this->preview($version, 10, [['quantity' => 10, 'selections' => $selections]]), 'package');
    }

    public function test_postgresql_rejects_cross_family_options_negative_prices_and_published_status(): void
    {
        $admin = $this->actor();
        [, $version] = $this->draft($admin, 'belly_lechon');
        $main = $this->option($version->family, 'portion');
        $this->group($admin, $version, 'rice', 'fixed', [$main]);
        $group = $version->fresh()->groups()->sole();
        $foreign = $this->option($this->family('food_pack'), 'portion');
        foreach ([
            fn () => DB::table('group_options')->insert(['group_id' => $group->id,
                'offering_option_id' => $foreign->id, 'price_adjustment_minor' => 0]),
            fn () => DB::table('group_options')->where('group_id', $group->id)->update(['price_adjustment_minor' => -1]),
            fn () => DB::table('package_versions')->where('id', $version->id)->update(['status' => 'published']),
            fn () => DB::table('package_versions')->where('id', $version->id)->update(['price_basis' => 'per_pax']),
            fn () => DB::table('selection_groups')->where('id', $group->id)->update(['slot_kind' => 'lechon']),
        ] as $index => $write) {
            DB::statement('SAVEPOINT package_constraint_test');
            try {
                $write();
                $this->fail('PostgreSQL accepted invalid package data at case '.$index);
            } catch (QueryException $exception) {
                $this->assertSame('23514', $exception->errorInfo[0]);
            } finally {
                DB::statement('ROLLBACK TO SAVEPOINT package_constraint_test');
            }
        }
    }

    public function test_independent_quantities_revision_guard_and_overflow_are_enforced(): void
    {
        $admin = $this->actor();
        [$product, $version] = $this->draft($admin, 'food_pack', ['base_price_minor' => '100']);
        $this->assertSame(10, $version->minimum_quantity);
        $this->expectInvalid(fn () => app(SavePackageVersion::class)->handle($admin, $product, [
            'code' => 'standard', 'name' => 'Standard', 'active' => true,
            'base_price_minor' => '100', 'selection_policy' => 'uniform',
            'minimum_quantity' => 1, 'quantity_step' => 1,
            'maximum_quantity' => null,
            'draft_revision' => 0,
        ], $version), 'draft_revision');
        $this->assertSame(2, $version->fresh()->draft_revision);
        $this->expectInvalid(fn () => app(SavePackageVersion::class)->handle($admin, $product, [
            'code' => 'standard', 'name' => 'Standard', 'active' => true,
            'base_price_minor' => '100', 'selection_policy' => 'uniform',
            'minimum_quantity' => 21, 'quantity_step' => 1,
            'maximum_quantity' => 20,
            'draft_revision' => 1,
        ], $version), 'maximum_quantity');
        [, $main] = $this->draft($admin, 'belly_lechon', ['base_price_minor' => (string) PHP_INT_MAX]);
        $family = $main->family;
        $this->group($admin, $main, 'rice', 'fixed', [$this->option($family, 'portion')]);
        $drink = $this->option($family, 'bottle');
        $this->group($admin, $main, 'drink', 'select_one', [$drink], 1, 1, [
            'options' => [['offering_option_id' => $drink->id, 'is_default' => true, 'price_adjustment_minor' => '0']],
        ]);
        $this->group($admin, $main, 'dish', 'select_many', [$this->option($family)]);
        $this->expectInvalid(fn () => $this->preview($main, 150, [[
            'quantity' => 150, 'selections' => $this->selections($main),
        ]]), 'price');
    }

    public function test_staff_needs_both_edit_and_pricing_permission_to_save_package_prices(): void
    {
        $admin = $this->actor();
        $staff = $this->actor('staff');
        $family = $this->family('belly_lechon');
        ServiceConfiguration::configure($family);
        $this->signIn($staff)->post(route('packages.store'), [
            'family_id' => $family->id, 'code' => 'basic', 'name' => 'Basic', 'description' => null, 'active' => true,
            'creation_token' => '64d203ad-889a-42b7-8f50-b31838999b4c',
        ])->assertForbidden();
        $this->grant($staff, 'package.manage');
        $this->post(route('packages.store'), [
            'family_id' => $family->id, 'code' => 'basic', 'name' => 'Basic', 'description' => null, 'active' => true,
            'creation_token' => '64d203ad-889a-42b7-8f50-b31838999b4c',
        ])->assertRedirect();
        $product = Product::where('name', 'Basic')->sole();
        $draft = $product->versions()->sole();
        $payload = ['code' => 'premium', 'name' => 'Premium', 'active' => true,
            'draft_revision' => $draft->draft_revision, 'base_price_minor' => '10000', 'selection_policy' => 'uniform',
            'minimum_quantity' => 1, 'quantity_step' => 1,
            'maximum_quantity' => null];
        $this->put(route('packages.versions.update', [$product, $draft]), $payload)->assertForbidden();
        $this->grant($staff, 'package.pricing.manage');
        $this->put(route('packages.versions.update', [$product, $draft]), $payload)->assertRedirect();
        $this->assertSame(1, PackageVersion::count());
        $staff->update(['active' => false]);
        $this->put(route('packages.versions.update', [$product, $draft]), $payload)->assertRedirect(route('login'));
        $this->assertSame(1, PackageVersion::count());
        $this->signIn($admin)->get(route('catering.index'))->assertOk();
    }

    public function test_admin_preview_is_not_customer_bookable_and_staff_direct_requests_are_authorized(): void
    {
        $admin = $this->actor();
        $staff = $this->actor('staff');
        [$product, $version] = $this->draft($admin, 'pica_pica');
        $option = $this->option($version->family, 'piece');
        $this->group($admin, $version, 'bundle_item', 'fixed', [$option]);
        $path = route('packages.versions.preview', [$product, $version]);
        $payload = ['quantity' => 1, 'groups' => [[
            'quantity' => 1, 'selections' => $this->selections($version),
        ]], 'total_minor' => '1'];
        $this->postJson($path, $payload)->assertUnauthorized();
        $this->signIn($staff)->postJson($path, $payload)->assertForbidden();
        $this->grant($staff, 'package.view');
        $this->postJson($path, $payload)->assertOk()->assertJsonPath('total_minor', '10000');
        $this->post(route('packages.groups.store', [$product, $version]), [
            'group_code' => 'forged',
        ])->assertForbidden();
        $this->assertDatabaseMissing('selection_groups', ['group_code' => 'forged']);
        $this->assertSame('draft', $version->fresh()->status);
        $this->assertSame(0, DB::table('package_versions')->where('status', 'published')->count());
    }
}
