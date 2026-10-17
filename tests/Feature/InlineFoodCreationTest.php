<?php

namespace Tests\Feature;

use App\Actions\Catalog\SaveDish;
use App\Models\AuditEvent;
use App\Models\Dish;
use App\Models\DishCategory;
use App\Models\Role;
use App\Models\User;
use App\Services\Catalog\FoodImages;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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

    public function test_inline_json_creation_returns_saved_food_and_category_without_navigation(): void
    {
        $response = $this->postJson(route('catalog.dishes.store'), $this->input());
        $response->assertCreated()->assertJsonPath('food.name', 'Fried Rice')->assertJsonPath('food.category.name', 'Rice');
        $this->assertSame(Dish::sole()->id, $response->json('food.id'));
        $this->assertSame(DishCategory::where('name', 'Rice')->sole()->id, $response->json('food.category_id'));
        $response->assertJsonMissingPath('food.image_path');
        $this->assertSame(1, AuditEvent::where('action', 'catalog.dish_created')->count());
        $this->postJson(route('catalog.dishes.store'), [...$this->input(), 'name' => ['invalid']])->assertUnprocessable();
        // Multipart form submissions supply foreign keys as strings; the JSON contract must not.
        $categoryId = $response->json('food.category_id');
        $this->post(route('catalog.dishes.store'), ['name' => 'Steamed Rice', 'category_id' => (string) $categoryId, 'active' => '1'], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('food.category_id', $categoryId);
    }

    public function test_food_failure_rolls_back_inline_category_and_audit(): void
    {
        $before = AuditEvent::count();
        $this->mock(FoodImages::class)->shouldReceive('store')->andThrow(ValidationException::withMessages(['image' => 'Storage unavailable.']));
        try {
            app(SaveDish::class)->handle($this->admin, [...$this->input(), 'image' => UploadedFile::fake()->image('food.jpg')]);
            $this->fail('Expected storage failure.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('image', $exception->errors());
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
