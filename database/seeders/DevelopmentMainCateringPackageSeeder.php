<?php

namespace Database\Seeders;

use App\Actions\Catalog\EnsurePrimaryService;
use App\Actions\Package\SavePackageVersion;
use App\Actions\Package\SaveProduct;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Draft-only commercial fixtures. Menu composition remains an owner decision. */
class DevelopmentMainCateringPackageSeeder extends Seeder
{
    /** @var array<string, array{price: string, token: string}> */
    private const PACKAGES = [
        'Package A' => ['price' => '380.00', 'token' => 'fb7d6a71-84cd-4ce0-9800-000000000001'],
        'Package B' => ['price' => '450.00', 'token' => 'fb7d6a71-84cd-4ce0-9800-000000000002'],
        'Package C' => ['price' => '480.00', 'token' => 'fb7d6a71-84cd-4ce0-9800-000000000003'],
        'Package D' => ['price' => '520.00', 'token' => 'fb7d6a71-84cd-4ce0-9800-000000000004'],
    ];

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
        foreach (self::PACKAGES as $name => $fixture) {
            DB::transaction(function () use ($actor, $family, $name, $fixture): void {
                $token = $fixture['token'];
                $product = Product::where('creation_token', $token)->lockForUpdate()->first();
                if ($product) {
                    if ($product->family_id !== $family->id) {
                        throw new \RuntimeException("Demo package identity {$token} belongs to a different record.");
                    }

                    // The immutable creation token establishes fixture ownership. Preserve any
                    // subsequent Administrator edits to this package and its Draft.
                    return;
                }
                $product = app(SaveProduct::class)->handle($actor, [
                    'family_id' => $family->id,
                    'name' => $name,
                    'active' => true,
                    'code' => 'demo_main_'.strtolower(str_replace(' ', '_', $name)),
                    'creation_token' => $token,
                ]);
                $draft = $product->versions()->where('status', 'draft')->sole();
                app(SavePackageVersion::class)->handle($actor, $product, [
                    'draft_revision' => $draft->draft_revision,
                    'base_price_pesos' => $fixture['price'],
                    'price_basis' => 'per_pax',
                    'selection_policy' => 'uniform',
                    'minimum_quantity' => 150,
                    'quantity_step' => 50,
                    'maximum_quantity' => null,
                ], $draft);
            });
        }
    }
}
