<?php

namespace App\Providers;

use App\Models\Dish;
use App\Models\DishCategory;
use App\Models\DishOffering;
use App\Models\FamilyOrderDefault;
use App\Models\OfferingOption;
use App\Models\Product;
use App\Models\ProductFamily;
use App\Models\ProductVariant;
use App\Models\SelectionGroup;
use App\Models\User;
use App\Models\VariantVersion;
use App\Policies\CatalogPolicy;
use App\Policies\FamilyOrderDefaultPolicy;
use App\Policies\PackagePolicy;
use App\Policies\ProductFamilyPolicy;
use App\Services\Access\PermissionResolver;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(PermissionResolver::class);
    }

    public function boot(): void
    {
        Gate::define('dashboard.view', fn (User $user) => app(PermissionResolver::class)->allows($user, 'dashboard.view'));
        foreach ([DishCategory::class, Dish::class, DishOffering::class, OfferingOption::class] as $model) {
            Gate::policy($model, CatalogPolicy::class);
        }
        Gate::policy(FamilyOrderDefault::class, FamilyOrderDefaultPolicy::class);
        Gate::policy(ProductFamily::class, ProductFamilyPolicy::class);
        foreach ([Product::class, ProductVariant::class, VariantVersion::class, SelectionGroup::class] as $model) {
            Gate::policy($model, PackagePolicy::class);
        }
        // No global Admin Gate bypass: Policies must retain last-Admin/target protections.
    }
}
