<?php

namespace App\Providers;

use App\Models\BusinessPolicy;
use App\Models\CancellationRequest;
use App\Models\Dish;
use App\Models\DishCategory;
use App\Models\DishOffering;
use App\Models\FamilyOrderDefault;
use App\Models\IndividualListing;
use App\Models\ListingVersion;
use App\Models\MeasurementUnit;
use App\Models\OfferingOption;
use App\Models\PackageVersion;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PencilBooking;
use App\Models\Product;
use App\Models\ProductFamily;
use App\Models\Quotation;
use App\Models\QuotationAdjustmentRequest;
use App\Models\QuotationTermsVersion;
use App\Models\QuotationVersion;
use App\Models\Reservation;
use App\Models\ReservationRequest;
use App\Models\SelectionGroup;
use App\Models\User;
use App\Policies\BusinessPolicyPolicy;
use App\Policies\CancellationRequestPolicy;
use App\Policies\CatalogPolicy;
use App\Policies\FamilyOrderDefaultPolicy;
use App\Policies\ListingPolicy;
use App\Policies\PackagePolicy;
use App\Policies\PaymentPolicy;
use App\Policies\PencilBookingPolicy;
use App\Policies\ProductFamilyPolicy;
use App\Policies\QuotationPolicy;
use App\Policies\QuotationTermsVersionPolicy;
use App\Policies\ReservationPolicy;
use App\Policies\ReservationRequestPolicy;
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
        foreach ([DishCategory::class, Dish::class, DishOffering::class, OfferingOption::class, MeasurementUnit::class] as $model) {
            Gate::policy($model, CatalogPolicy::class);
        }
        Gate::policy(FamilyOrderDefault::class, FamilyOrderDefaultPolicy::class);
        Gate::policy(ProductFamily::class, ProductFamilyPolicy::class);
        Gate::policy(ReservationRequest::class, ReservationRequestPolicy::class);
        Gate::policy(Reservation::class, ReservationPolicy::class);
        Gate::policy(CancellationRequest::class, CancellationRequestPolicy::class);
        Gate::policy(BusinessPolicy::class, BusinessPolicyPolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);
        Gate::policy(PaymentMethod::class, PaymentPolicy::class);
        Gate::policy(PencilBooking::class, PencilBookingPolicy::class);
        Gate::policy(QuotationTermsVersion::class, QuotationTermsVersionPolicy::class);
        foreach ([Quotation::class, QuotationVersion::class, QuotationAdjustmentRequest::class] as $model) {
            Gate::policy($model, QuotationPolicy::class);
        }
        Gate::policy(IndividualListing::class, ListingPolicy::class);
        Gate::policy(ListingVersion::class, ListingPolicy::class);
        foreach ([Product::class, PackageVersion::class, SelectionGroup::class] as $model) {
            Gate::policy($model, PackagePolicy::class);
        }
        // No global Admin Gate bypass: Policies must retain last-Admin/target protections.
    }
}
