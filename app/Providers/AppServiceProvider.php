<?php

namespace App\Providers;

use App\Models\Dish;
use App\Models\DishCategory;
use App\Models\DishOffering;
use App\Models\FamilyOrderDefault;
use App\Models\IndividualListing;
use App\Models\ListingVersion;
use App\Models\OfferingOption;
use App\Models\PackageVersion;
use App\Models\Product;
use App\Models\ProductFamily;
use App\Models\SelectionGroup;
use App\Models\User;
use App\Policies\CatalogPolicy;
use App\Policies\FamilyOrderDefaultPolicy;
use App\Policies\ListingPolicy;
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
        Gate::policy(IndividualListing::class, ListingPolicy::class);
        Gate::policy(ListingVersion::class, ListingPolicy::class);
        foreach ([Product::class, PackageVersion::class, SelectionGroup::class] as $model) {
            Gate::policy($model, PackagePolicy::class);
        }
        // No global Admin Gate bypass: Policies must retain last-Admin/target protections.
    }
}
