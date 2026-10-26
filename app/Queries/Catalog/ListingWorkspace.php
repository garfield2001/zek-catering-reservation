<?php

namespace App\Queries\Catalog;

use App\Models\Dish;
use App\Models\IndividualListing;
use App\Models\ProductFamily;
use App\Services\Catalog\ListingConfiguration;
use Illuminate\Support\Facades\Gate;

class ListingWorkspace
{
    public function editor(ProductFamily $family, ?IndividualListing $listing = null): array
    {
        Gate::authorize($listing ? 'update' : 'create', $listing ?? IndividualListing::class);
        if (! $listing) {
            Gate::authorize('price', IndividualListing::class);
        }
        abort_unless($family->behavior_type === 'direct_sale' && ! $family->archived_at && ! $listing?->archived_at, 404);
        $version = $listing?->versions()->where('status', 'draft')->first() ?? $listing?->currentVersion;
        if ($version?->status === 'published') {
            Gate::authorize('history', IndividualListing::class);
        }
        $version?->load('sellingOptions');
        $foods = Dish::where('active', true)->whereHas('category', fn ($q) => $q->where('active', true))
            ->with(['offerings' => fn ($q) => $q->where('family_id', $family->id)->where('active', true), 'offerings.options' => fn ($q) => $q->where('active', true)])
            ->orderBy('name')->get();

        return ['family' => $family, 'listing' => $listing, 'version' => $version, 'foods' => $foods,
            'canPrice' => Gate::allows('price', IndividualListing::class)];
    }

    public function bulkEditor(ProductFamily $family): array
    {
        Gate::authorize('create', IndividualListing::class);
        Gate::authorize('price', IndividualListing::class);
        abort_unless($family->behavior_type === 'direct_sale' && $family->active && ! $family->archived_at, 404);
        $configured = $family->listings()
            ->with('dish.category')
            ->get()
            ->keyBy('dish_id');
        $foods = Dish::query()
            ->where('active', true)
            ->whereHas('category', fn ($query) => $query->where('active', true))
            ->with('category')
            ->orderBy('category_id')
            ->orderBy('name')
            ->get()
            ->map(fn (Dish $dish) => [
                ...$dish->only('id', 'name', 'category_id', 'thumbnail_url'),
                'category' => $dish->category?->only('id', 'name'),
                'existing_listing_id' => $configured->get($dish->id)?->id,
            ]);

        return ['family' => $family, 'foods' => $foods,
            'categories' => $foods->pluck('category')->filter()->unique('id')->values(),
            'canPrice' => Gate::allows('price', IndividualListing::class)];
    }

    public function details(IndividualListing $listing, ?int $versionId = null): array
    {
        Gate::authorize('view', $listing);
        $listing->load(['family', 'dish.category', 'servingOption.offering']);
        $version = $versionId ? $listing->versions()->findOrFail($versionId) : ($listing->currentVersion ?? $listing->versions()->where('status', 'draft')->firstOrFail());
        $canManage = Gate::allows('update', $listing);
        if ($version->status !== 'published') {
            abort_unless($canManage || Gate::allows('publish', IndividualListing::class), 403);
        }
        if ($version->status === 'published' && $version->id !== $listing->current_version_id) {
            Gate::authorize('history', IndividualListing::class);
        }
        $configuration = app(ListingConfiguration::class);

        return ['listing' => $listing->only('id', 'family_id', 'active', 'archived_at', 'current_version_id'),
            'commercialHistory' => Gate::allows('history', IndividualListing::class) ? app(CommercialHistory::class)->forOwner($listing) : null,
            'snapshot' => $version->status === 'published' ? $version->snapshot() : $configuration->snapshot($listing, $version),
            'version' => $version->only('id', 'version_number', 'status', 'draft_revision', 'published_at'),
            'image_url' => $listing->dish->image_url,
            'thumbnail_url' => $listing->dish->thumbnail_url,
            'availability' => $configuration->availability($listing),
            'draft_id' => ($canManage || Gate::allows('publish', IndividualListing::class)) ? $listing->versions()->where('status', 'draft')->value('id') : null,
            'history' => Gate::allows('history', IndividualListing::class) ? $listing->versions()->where('status', '!=', 'draft')->get(['id', 'version_number', 'status', 'published_at']) : [],
            'canManage' => $canManage && ($listing->versions()->where('status', 'draft')->exists() || Gate::allows('history', IndividualListing::class)), 'canPublish' => Gate::allows('publish', IndividualListing::class),
            'canHistory' => Gate::allows('history', IndividualListing::class), 'canLifecycle' => Gate::allows('lifecycle', IndividualListing::class)];
    }
}
