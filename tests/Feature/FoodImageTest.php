<?php

namespace Tests\Feature;

use App\Actions\Catalog\SaveDish;
use App\Models\AuditEvent;
use App\Models\Dish;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FoodImageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('catalog');
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->roles()->attach(Role::where('code', 'admin')->sole());
        $this->actingAs($this->admin)->withSession(['access_version' => $this->admin->access_version]);
    }

    private function data(): array
    {
        return ['name' => 'Fried Rice', 'new_category_name' => 'Rice', 'active' => true, 'family_ids' => [], 'image' => UploadedFile::fake()->image('rice.png', 1800, 1200)];
    }

    public function test_upload_is_reencoded_with_generated_paths_thumbnail_and_safe_urls(): void
    {
        $this->post(route('catalog.dishes.store'), $this->data())->assertSessionHasNoErrors();
        $food = Dish::sole();
        $this->assertSame(1600, $food->image_width);
        $this->assertSame(1067, $food->image_height);
        $this->assertMatchesRegularExpression('/^foods\/[a-z0-9]+\.jpg$/', $food->image_path);
        Storage::disk('catalog')->assertExists([$food->image_path, $food->image_thumbnail_path]);
        $thumb = getimagesizefromstring(Storage::disk('catalog')->get($food->image_thumbnail_path));
        $this->assertSame(360, $thumb[0]);
        $this->assertSame('image/jpeg', $thumb['mime']);
        $this->assertArrayNotHasKey('image_path', $food->toArray());
        $this->get($food->image_url)->assertOk()->assertHeader('Content-Type', 'image/jpeg')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get('/media/foods/'.$food->id.'/invalid')->assertNotFound();
        Storage::disk('catalog')->delete($food->image_path);
        $this->get($food->image_url)->assertNotFound();
    }

    public function test_invalid_and_unauthorized_uploads_do_not_store_files(): void
    {
        foreach ([UploadedFile::fake()->create('bad.svg', 1, 'image/svg+xml'), UploadedFile::fake()->image('wide.jpg', 3001, 20), UploadedFile::fake()->createWithContent('fake.jpg', '<?php echo 1;')] as $upload) {
            $this->post(route('catalog.dishes.store'), [...$this->data(), 'image' => $upload])->assertSessionHasErrors('image');
        }
        $staff = User::factory()->create();
        $staff->roles()->attach(Role::where('code', 'staff')->sole());
        $this->actingAs($staff)->withSession(['access_version' => $staff->access_version])->post(route('catalog.dishes.store'), $this->data())->assertForbidden();
        $this->assertSame([], Storage::disk('catalog')->allFiles());
        $this->assertSame(0, Dish::count());
    }

    public function test_audit_failure_and_outer_rollback_remove_new_files(): void
    {
        $data = $this->data();
        DB::beginTransaction();
        app(SaveDish::class)->handle($this->admin, $data);
        $this->assertCount(2, Storage::disk('catalog')->allFiles());
        DB::rollBack();
        $this->assertSame([], Storage::disk('catalog')->allFiles());
        $this->mock(AuditLogger::class)->shouldReceive('record')->andReturnUsing(function ($action) {
            if ($action === 'catalog.dish_created') {
                throw new \RuntimeException('Simulated audit failure.');
            }

            return new AuditEvent;
        });
        try {
            app(SaveDish::class)->handle($this->admin, $data);
            $this->fail('Expected rollback.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated audit failure.', $exception->getMessage());
        }
        $this->assertSame([], Storage::disk('catalog')->allFiles());
        $this->assertSame(0, Dish::count());
    }

    public function test_replace_and_remove_preserve_old_files_until_commit_and_reject_staff_mutation(): void
    {
        $this->post(route('catalog.dishes.store'), $this->data())->assertSessionHasNoErrors();
        $food = Dish::sole();
        $old = $food->image_path;
        $data = ['name' => $food->name, 'category_id' => $food->category_id, 'active' => true, 'family_ids' => []];
        DB::beginTransaction();
        $pending = app(SaveDish::class)->handle($this->admin, [...$data, 'image' => UploadedFile::fake()->image('rollback.jpg')], $food);
        $pendingPath = $pending->image_path;
        Storage::disk('catalog')->assertExists($old);
        DB::rollBack();
        Storage::disk('catalog')->assertMissing($pendingPath);
        Storage::disk('catalog')->assertExists($old);
        $this->assertSame($old, $food->fresh()->image_path);
        $this->post(route('catalog.dishes.update', $food), [...$data, '_method' => 'put', 'image' => UploadedFile::fake()->image('new.jpg')])->assertSessionHasNoErrors();
        $food->refresh();
        $this->assertNotSame($old, $food->image_path);
        // Laravel's test manager treats the enclosing RefreshDatabase transaction
        // as committed for callbacks; a completed request cleans the replaced file.
        Storage::disk('catalog')->assertMissing($old);
        $this->post(route('catalog.dishes.update', $food), [...$data, '_method' => 'put', 'remove_image' => true])->assertSessionHasNoErrors();
        $this->assertNull($food->fresh()->image_url);
        $staff = User::factory()->create();
        $staff->roles()->attach(Role::where('code', 'staff')->sole());
        $this->actingAs($staff)->withSession(['access_version' => $staff->access_version])->put(route('catalog.dishes.update', $food), [...$data, 'remove_image' => true])->assertForbidden();
    }
}
