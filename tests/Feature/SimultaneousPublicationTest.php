<?php

namespace Tests\Feature;

use App\Actions\Package\SaveProduct;
use App\Actions\Package\SaveSelectionGroup;
use App\Models\Dish;
use App\Models\DishCategory;
use App\Models\DishOffering;
use App\Models\OfferingOption;
use App\Models\ProductFamily;
use App\Models\Role;
use App\Models\User;
use App\Models\VariantVersion;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\Support\LegacyCollections;
use Tests\TestCase;

class SimultaneousPublicationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertStringEndsWith('_test', (string) config('database.connections.pgsql.database'));
        Artisan::call('migrate:fresh', ['--env' => 'testing', '--force' => true]);
        $this->seed(DatabaseSeeder::class);
        LegacyCollections::seed();
    }

    public function test_independent_processes_publish_the_same_draft_without_duplicate_history(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('code', 'admin')->sole());
        $family = ProductFamily::where('code', 'main_catering')->sole();
        $product = app(SaveProduct::class)->handle($admin, [
            'family_id' => $family->id, 'name' => 'Concurrent Package', 'active' => true,
        ]);
        $version = $product->variants()->sole()->versions()->sole();
        $category = DishCategory::create(['code' => 'concurrent', 'name' => 'Concurrent', 'sort_order' => 1, 'active' => true]);
        $dish = Dish::create(['code' => 'concurrent_dish', 'category_id' => $category->id, 'name' => 'Concurrent Dish', 'active' => true]);
        DB::table('dish_family_eligibilities')->insert(['dish_id' => $dish->id, 'family_id' => $family->id, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $offering = DishOffering::create(['dish_id' => $dish->id, 'family_id' => $family->id, 'code' => 'standard', 'name' => 'Standard', 'active' => true]);
        $option = OfferingOption::create(['offering_id' => $offering->id, 'option_code' => 'regular', 'label' => 'Regular', 'unit' => 'tray', 'quantity' => '1.000', 'active' => true]);
        app(SaveSelectionGroup::class)->handle($admin, $version, [
            'draft_revision' => 1, 'name' => 'Concurrent Choice', 'mode' => 'select_one', 'slot_kind' => 'drink',
            'minimum_count' => 1, 'maximum_count' => 1, 'unique_dishes' => true, 'category_mode' => 'free',
            'inclusion_count' => '1.000', 'options' => [['offering_option_id' => $option->id, 'is_default' => true, 'price_adjustment_minor' => '0']],
            'category_rules' => [], 'dish_rules' => [],
        ]);
        $revision = $version->fresh()->draft_revision;
        $base = base_path('tests/Support/concurrent_publish.php');
        $processes = array_map(fn () => new Process([
            PHP_BINARY, $base, '--user='.$admin->id, '--version='.$version->id, '--revision='.$revision,
        ], base_path(), ['APP_ENV' => 'testing']), [1, 2]);
        foreach ($processes as $process) {
            $process->start();
        }
        foreach ($processes as $process) {
            $process->wait();
        }
        $this->assertTrue(collect($processes)->every(fn (Process $process) => $process->isSuccessful()), collect($processes)->map(fn (Process $process) => $process->getErrorOutput())->join("\n"));
        $this->assertSame('published', $version->fresh()->status);
        $this->assertSame($version->id, $version->variant->fresh()->current_version_id);
        $this->assertSame(1, VariantVersion::where('variant_id', $version->variant_id)->where('status', 'published')->count());
    }
}
