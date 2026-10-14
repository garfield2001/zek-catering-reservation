<?php

namespace Tests\Feature;

use App\Actions\Catalog\SaveDish;
use App\Models\AuditEvent;
use App\Models\Dish;
use App\Models\DishCategory;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InlineFoodCreationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->roles()->attach(Role::where('code', 'admin')->sole());
        $this->actingAs($this->admin)->withSession(['access_version' => $this->admin->access_version]);
    }

    private function input(): array
    {
        return ['name' => 'Fried Rice', 'new_category_name' => 'Rice', 'category_id' => null, 'active' => true, 'family_ids' => []];
    }

    public function test_inline_creation_persists_linked_food_and_category_with_audits(): void
    {
        $this->post(route('catalog.dishes.store'), $this->input())->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(DishCategory::where('name', 'Rice')->sole()->id, Dish::where('name', 'Fried Rice')->sole()->category_id);
        $this->assertSame(1, AuditEvent::where('action', 'catalog.category_created')->count());
        $this->assertSame(1, AuditEvent::where('action', 'catalog.dish_created')->count());
    }

    public function test_food_failure_rolls_back_inline_category_and_audit(): void
    {
        $before = AuditEvent::count();
        try {
            app(SaveDish::class)->handle($this->admin, [...$this->input(), 'family_ids' => [999999]]);
            $this->fail('Invalid eligibility was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('family_ids', $exception->errors());
        }
        $this->assertDatabaseMissing('dish_categories', ['name' => 'Rice']);
        $this->assertDatabaseMissing('dishes', ['name' => 'Fried Rice']);
        $this->assertSame($before, AuditEvent::count());
    }

    public function test_malformed_duplicate_and_ambiguous_category_input_is_rejected(): void
    {
        $this->post(route('catalog.dishes.store'), [...$this->input(), 'new_category_name' => ['Rice']])->assertSessionHasErrors('new_category_name');
        $this->post(route('catalog.dishes.store'), $this->input())->assertSessionHasNoErrors();
        $this->post(route('catalog.dishes.store'), [...$this->input(), 'new_category_name' => ' rice '])->assertSessionHasErrors('new_category_name');
        $this->post(route('catalog.dishes.store'), [...$this->input(), 'category_id' => DishCategory::where('name', 'Rice')->sole()->id])->assertSessionHasErrors('category_id');
        $this->assertSame(1, Dish::count());
    }

    public function test_unprivileged_staff_cannot_create_inline_category_or_food(): void
    {
        $staff = User::factory()->create();
        $staff->roles()->attach(Role::where('code', 'staff')->sole());
        $this->actingAs($staff)->withSession(['access_version' => $staff->access_version])
            ->post(route('catalog.dishes.store'), $this->input())->assertForbidden();
        $this->assertDatabaseMissing('dish_categories', ['name' => 'Rice']);
        $this->assertDatabaseMissing('dishes', ['name' => 'Fried Rice']);
    }
}
