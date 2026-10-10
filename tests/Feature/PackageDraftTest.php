<?php

namespace Tests\Feature;

use App\Actions\Package\SaveSelectionGroup;
use App\Actions\Package\SaveVariantDraft;
use App\Models\AuditEvent;
use App\Models\Dish;
use App\Models\DishCategory;
use App\Models\DishOffering;
use App\Models\OfferingOption;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductFamily;
use App\Models\Role;
use App\Models\User;
use App\Models\VariantVersion;
use App\Services\Catalog\PackagePricePreview;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Support\LegacyCollections;
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
        DB::table('dish_family_eligibilities')->insert(['dish_id' => $dish->id, 'family_id' => $family->id,
            'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $offering = DishOffering::create(['dish_id' => $dish->id, 'family_id' => $family->id,
            'code' => 'standard', 'name' => 'Standard', 'active' => true]);

        return OfferingOption::create(['offering_id' => $offering->id, 'option_code' => 'regular',
            'label' => 'Regular', 'unit' => $unit, 'quantity' => '1.000', 'active' => true]);
    }

    private function draft(User $actor, string $familyCode, array $changes = []): array
    {
        $family = $this->family($familyCode);
        $product = Product::create(['family_id' => $family->id, 'code' => 'package',
            'name' => 'Package', 'active' => true]);
        $data = ['code' => 'standard', 'name' => 'Standard', 'active' => true,
            'base_price_minor' => '10000', 'selection_policy' => 'uniform',
            'minimum_override' => null, 'step_override' => null,
            'maximum_override_enabled' => false, 'maximum_override' => null, ...$changes];
        $version = app(SaveVariantDraft::class)->handle($actor, $product, $data);

        return [$product, $version];
    }

    private function group(User $actor, VariantVersion $version, string $kind, string $mode,
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

    private function selections(VariantVersion $version, array $changes = []): array
    {
        $selected = [];
        foreach ($version->fresh()->groups as $group) {
            $selected[$group->group_code] = $group->options->take($group->minimum_count)->pluck('offering_option_id')->all();
        }

        return [...$selected, ...$changes];
    }

    private function preview(VariantVersion $version, int $quantity, array $allocations): array
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

    public function test_main_package_calculates_fixed_rice_drink_and_distinct_dishes_in_integer_minor_units(): void
    {
        $admin = $this->actor();
        [, $version] = $this->draft($admin, 'main_catering', ['base_price_minor' => '12555']);
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
        $this->assertSame('per_pax', $result['price_basis']);
        $this->assertSame('draft_preview', $result['status']);
        $this->assertSame('41250', $result['groups'][0]['adjustments'][0]['line_amount_minor']);
        $this->assertDatabaseHas('audit_events', ['action' => 'package.draft_created', 'subject_id' => $version->id]);
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
            'minimum_override' => 10, 'step_override' => 2, 'base_price_minor' => '12500']);
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

    public function test_staff_needs_both_package_manage_and_pricing_permission_to_save_variants(): void
    {
        $admin = $this->actor();
        $staff = $this->actor('staff');
        $family = $this->family('main_catering');
        $this->signIn($staff)->post(route('packages.store'), [
            'family_id' => $family->id, 'code' => 'basic', 'name' => 'Basic', 'description' => null, 'active' => true,
        ])->assertForbidden();
        $this->grant($staff, 'package.manage');
        $this->post(route('packages.store'), [
            'family_id' => $family->id, 'code' => 'basic', 'name' => 'Basic', 'description' => null, 'active' => true,
        ])->assertRedirect();
        $product = Product::where('code', 'basic')->sole();
        $payload = ['code' => 'standard', 'name' => 'Standard', 'active' => true,
            'base_price_minor' => '10000', 'selection_policy' => 'uniform',
            'minimum_override' => null, 'step_override' => null,
            'maximum_override_enabled' => false, 'maximum_override' => null];
        $this->post(route('packages.variants.store', $product), $payload)->assertForbidden();
        $this->grant($staff, 'package.pricing.manage');
        $this->post(route('packages.variants.store', $product), $payload)->assertRedirect();
        $this->assertSame(1, VariantVersion::count());
        $staff->update(['active' => false]);
        $this->post(route('packages.variants.store', $product), $payload)->assertRedirect(route('login'));
        $this->assertSame(1, VariantVersion::count());
        $this->signIn($admin)->get(route('packages.index'))->assertOk();
    }

    public function test_postgresql_rejects_cross_family_options_negative_prices_and_published_status(): void
    {
        $admin = $this->actor();
        [, $version] = $this->draft($admin, 'main_catering');
        $main = $this->option($version->family, 'portion');
        $this->group($admin, $version, 'rice', 'fixed', [$main]);
        $group = $version->fresh()->groups()->sole();
        $foreign = $this->option($this->family('food_pack'), 'portion');
        foreach ([
            fn () => DB::table('group_options')->insert(['group_id' => $group->id,
                'offering_option_id' => $foreign->id, 'price_adjustment_minor' => 0]),
            fn () => DB::table('group_options')->where('group_id', $group->id)->update(['price_adjustment_minor' => -1]),
            fn () => DB::table('variant_versions')->where('id', $version->id)->update(['status' => 'published']),
            fn () => DB::table('variant_versions')->where('id', $version->id)->update(['price_basis' => 'per_set']),
            fn () => DB::table('selection_groups')->where('id', $group->id)->update(['slot_kind' => 'lechon']),
        ] as $write) {
            DB::statement('SAVEPOINT package_constraint_test');
            try {
                $write();
                $this->fail('PostgreSQL accepted invalid package data.');
            } catch (QueryException $exception) {
                $this->assertSame('23514', $exception->errorInfo[0]);
            } finally {
                DB::statement('ROLLBACK TO SAVEPOINT package_constraint_test');
            }
        }
    }

    public function test_main_category_quotas_distinct_same_category_dishes_and_duplicate_rejection(): void
    {
        $admin = $this->actor();
        [, $version] = $this->draft($admin, 'main_catering');
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
        DB::table('dish_family_eligibilities')->where('dish_id', $option->offering->dish_id)
            ->where('family_id', $version->family_id)->update(['active' => false]);
        $this->expectInvalid(fn () => $this->preview($version, 1, $selection), 'groups.0.selections.'.$version->fresh()->groups()->sole()->group_code);
    }

    public function test_quantity_inheritance_revision_guard_and_overflow_are_enforced(): void
    {
        $admin = $this->actor();
        [$product, $version] = $this->draft($admin, 'food_pack', ['base_price_minor' => '100']);
        $this->assertSame(10, $version->minimum_quantity);
        $this->expectInvalid(fn () => app(SaveVariantDraft::class)->handle($admin, $product, [
            'code' => 'standard', 'name' => 'Standard', 'active' => true,
            'base_price_minor' => '100', 'selection_policy' => 'uniform',
            'minimum_override' => 1, 'step_override' => 1,
            'maximum_override_enabled' => false, 'maximum_override' => null,
            'draft_revision' => 0,
        ], $version->variant, $version), 'draft_revision');
        $this->assertSame(1, $version->fresh()->draft_revision);
        $this->expectInvalid(fn () => app(SaveVariantDraft::class)->handle($admin, $product, [
            'code' => 'standard', 'name' => 'Standard', 'active' => true,
            'base_price_minor' => '100', 'selection_policy' => 'uniform',
            'minimum_override' => null, 'step_override' => null,
            'maximum_override_enabled' => false, 'maximum_override' => 20,
            'draft_revision' => 1,
        ], $version->variant, $version), 'maximum_override');
        [, $main] = $this->draft($admin, 'main_catering', ['base_price_minor' => (string) PHP_INT_MAX]);
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

    public function test_admin_preview_is_not_customer_bookable_and_staff_direct_requests_are_authorized(): void
    {
        $admin = $this->actor();
        $staff = $this->actor('staff');
        [$product, $version] = $this->draft($admin, 'pica_pica');
        $option = $this->option($version->family, 'piece');
        $this->group($admin, $version, 'bundle_item', 'fixed', [$option]);
        $path = route('packages.versions.preview', [$product, $version->variant, $version]);
        $payload = ['quantity' => 1, 'groups' => [[
            'quantity' => 1, 'selections' => $this->selections($version),
        ]], 'total_minor' => '1'];
        $this->postJson($path, $payload)->assertUnauthorized();
        $this->signIn($staff)->postJson($path, $payload)->assertForbidden();
        $this->grant($staff, 'package.view');
        $this->postJson($path, $payload)->assertOk()->assertJsonPath('total_minor', '10000');
        $this->post(route('packages.groups.store', [$product, $version->variant, $version]), [
            'group_code' => 'forged',
        ])->assertForbidden();
        $this->assertDatabaseMissing('selection_groups', ['group_code' => 'forged']);
        $this->assertSame('draft', $version->fresh()->status);
        $this->assertSame(0, DB::table('variant_versions')->where('status', 'published')->count());
    }

    public function test_draft_slot_can_be_removed_with_audit_and_staff_cannot_bypass_authorization(): void
    {
        $admin = $this->actor();
        $staff = $this->actor('staff');
        [$product, $version] = $this->draft($admin, 'pica_pica');
        $this->group($admin, $version, 'bundle_item', 'fixed', [$this->option($version->family, 'piece')]);
        $group = $version->fresh()->groups()->sole();
        $path = route('packages.groups.destroy', [$product, $version->variant, $version, $group]);
        $this->signIn($staff)->delete($path)->assertForbidden();
        $this->assertDatabaseHas('selection_groups', ['id' => $group->id]);
        $this->signIn($admin)->delete($path)->assertRedirect();
        $this->assertDatabaseMissing('selection_groups', ['id' => $group->id]);
        $this->assertDatabaseMissing('group_options', ['group_id' => $group->id]);
        $this->assertDatabaseHas('audit_events', ['action' => 'package.group_deleted',
            'subject_type' => 'selection_group', 'subject_id' => $group->id]);
    }

    public function test_cross_slot_canonical_dish_duplicates_are_rejected_even_with_different_offerings(): void
    {
        $admin = $this->actor();
        [, $version] = $this->draft($admin, 'main_catering');
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
                'version_id' => $version->id, 'group_code' => $group->group_code,
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
        [$product, $version] = $this->draft($admin, 'main_catering');
        $option = $this->option($version->family, 'bottle');
        $this->group($admin, $version, 'drink', 'select_one', [$option], 1, 1, [
            'options' => [['offering_option_id' => $option->id, 'is_default' => true, 'price_adjustment_minor' => '0']],
        ]);
        $group = $version->fresh()->groups()->sole();
        $payload = ['group_code' => $group->group_code, 'name' => $group->name, 'mode' => 'select_one',
            'slot_kind' => 'drink', 'minimum_count' => 1, 'maximum_count' => 1,
            'unique_dishes' => true, 'category_mode' => 'free', 'inclusion_count' => '1.000',
            'sort_order' => 0, 'options' => [['offering_option_id' => $option->id,
                'is_default' => true, 'price_adjustment_minor' => '275']],
            'category_rules' => [], 'dish_rules' => []];
        $path = route('packages.groups.update', [$product, $version->variant, $version, $group]);
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
        [, $version] = $this->draft($admin, 'main_catering');
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

    public function test_additional_renamed_variant_preserves_groups_and_independent_structure(): void
    {
        $admin = $this->actor();
        [$product, $first] = $this->draft($admin, 'main_catering');
        $rice = $this->option($first->family, 'portion');
        $this->group($admin, $first, 'rice', 'fixed', [$rice]);
        $firstDrink = $this->option($first->family, 'bottle');
        $this->group($admin, $first, 'drink', 'select_one', [$firstDrink], 1, 1, [
            'options' => [['offering_option_id' => $firstDrink->id, 'is_default' => true, 'price_adjustment_minor' => '0']],
        ]);
        $this->group($admin, $first, 'dish', 'select_many', [$this->option($first->family)]);
        foreach (['b', 'c', 'd', 'premium'] as $index => $code) {
            $version = app(SaveVariantDraft::class)->handle($admin, $product, [
                'code' => $code, 'name' => $code === 'premium' ? 'Premium Package' : strtoupper($code),
                'description' => $code === 'premium' ? 'More dish choices' : null,
                'sort_order' => ($index + 1) * 10, 'active' => true,
                'base_price_minor' => '12000', 'selection_policy' => 'uniform',
                'minimum_override' => null, 'step_override' => null,
                'maximum_override_enabled' => false, 'maximum_override' => null,
            ]);
        }
        $this->assertSame(5, $product->variants()->count());
        $this->assertSame('Premium Package', $version->variant->name);
        $this->group($admin, $version, 'rice', 'fixed', [$rice]);
        $secondDrink = $this->option($version->family, 'bottle');
        $this->group($admin, $version, 'drink', 'select_one', [$secondDrink], 1, 1, [
            'options' => [['offering_option_id' => $secondDrink->id, 'is_default' => true, 'price_adjustment_minor' => '0']],
        ]);
        $firstDish = $this->option($version->family);
        $secondDish = $this->option($version->family);
        $this->group($admin, $version, 'dish', 'select_many', [$firstDish, $secondDish], 2, 2, [
            'options' => [
                ['offering_option_id' => $firstDish->id, 'is_default' => true, 'price_adjustment_minor' => '0'],
                ['offering_option_id' => $secondDish->id, 'is_default' => true, 'price_adjustment_minor' => '0'],
            ],
        ]);
        $this->assertSame('1800000', $this->preview($version, 150, [[
            'quantity' => 150, 'selections' => $this->selections($version),
        ]])['total_minor']);
        $dishSlot = $version->fresh()->groups->firstWhere('slot_kind', 'dish');
        $invalidSelections = $this->selections($version);
        $invalidSelections[$dishSlot->group_code] = [$firstDish->id];
        try {
            $this->preview($version, 150, [['quantity' => 150, 'selections' => $invalidSelections]]);
            $this->fail('A required second dish was omitted.');
        } catch (ValidationException $exception) {
            $message = $exception->errors()['groups.0.selections.'.$dishSlot->group_code][0];
            $this->assertStringContainsString('exactly 2', $message);
            $this->assertStringContainsString($dishSlot->name, $message);
        }
        $this->assertSame(1, $first->fresh()->groups->firstWhere('slot_kind', 'dish')->minimum_count);
        $renamed = app(SaveVariantDraft::class)->handle($admin, $product, [
            'code' => 'premium', 'name' => 'Celebration Package', 'description' => 'Renamed package',
            'sort_order' => 99, 'active' => true, 'base_price_minor' => '12000',
            'selection_policy' => 'uniform', 'minimum_override' => null,
            'step_override' => null, 'maximum_override_enabled' => false, 'maximum_override' => null,
            'draft_revision' => $version->draft_revision,
        ], $version->variant, $version);
        $this->assertSame($version->id, $renamed->id);
        $this->assertSame('premium', $renamed->variant->code);
        $this->assertSame('Celebration Package', $renamed->variant->name);
        $this->assertSame(3, $renamed->groups()->count());
        $this->assertSame('1800000', $this->preview($renamed, 150, [[
            'quantity' => 150, 'selections' => $this->selections($renamed),
        ]])['total_minor']);
        DB::statement('SAVEPOINT variant_display_name_test');
        try {
            DB::table('product_variants')->insert(['product_id' => $product->id,
                'code' => 'duplicate_label', 'name' => '  CELEBRATION PACKAGE  ',
                'created_at' => now(), 'updated_at' => now()]);
            $this->fail('PostgreSQL accepted a duplicate variant display name.');
        } catch (QueryException $exception) {
            $this->assertSame('23505', $exception->errorInfo[0]);
        } finally {
            DB::statement('ROLLBACK TO SAVEPOINT variant_display_name_test');
        }
    }
}
