<?php

namespace App\Http\Controllers\Admin\Package;

use App\Actions\Catalog\EnsurePrimaryService;
use App\Actions\Package\ClonePackageDraft;
use App\Actions\Package\SavePackageMenuConfiguration;
use App\Actions\Package\SavePackageVersion;
use App\Actions\Package\SaveProduct;
use App\Http\Controllers\Controller;
use App\Http\Requests\Package\SaveMainCateringMenuRequest;
use App\Http\Requests\Package\SaveMainCateringPricingRequest;
use App\Http\Requests\Package\SaveProductRequest;
use App\Models\PackageVersion;
use App\Models\Product;
use App\Queries\Package\PackageEditor;
use App\Services\Package\PackageMenuConfiguration;
use App\Services\Package\PublishedSnapshot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Routes the Main Catering Draft through persisted, business-focused pages. */
class MainCateringWorkflowController extends Controller
{
    public function create(EnsurePrimaryService $primary): Response|RedirectResponse
    {
        Gate::authorize('create', Product::class);
        Gate::authorize('price', PackageVersion::class);
        $family = $primary->handle();
        if (! $family->active) {
            return redirect()->route('catering.index')->withErrors([
                'family_id' => "{$family->name} is disabled for new package drafts. Enable it before creating a package.",
            ]);
        }

        return Inertia::render('admin/catering/details', [
            'family' => $family,
            'product' => null,
        ]);
    }

    public function store(SaveProductRequest $request, SaveProduct $save, EnsurePrimaryService $primary): RedirectResponse
    {
        Gate::authorize('price', PackageVersion::class);
        $product = $save->handle($request->user(), [...$request->validated(), 'family_id' => $primary->handle()->id]);

        return redirect()->route('catering.pricing', $product)
            ->with('status', 'Package details saved. Set the guest pricing before configuring the menu.');
    }

    public function details(Product $product): Response
    {
        $this->guard($product);

        return Inertia::render('admin/catering/details', [
            'family' => $product->family,
            'product' => $product,
        ]);
    }

    /** Start an explicit, editable revision without ever mutating a Published Version. */
    public function startDraft(Product $product, ClonePackageDraft $clone): RedirectResponse
    {
        $this->guard($product);
        $source = $product->currentVersion;
        if ($source === null) {
            throw ValidationException::withMessages([
                'package' => 'This package does not yet have a published version to revise.',
            ]);
        }

        $clone->handle(request()->user(), $product, $source);

        return redirect()->route('catering.details', $product)
            ->with('status', 'An editable Draft was created from the published version. The published version remains unchanged.');
    }

    public function updateDetails(SaveProductRequest $request, Product $product, SaveProduct $save): RedirectResponse
    {
        $this->guard($product);
        $save->handle($request->user(), $request->validated(), $product);

        return redirect()->route('catering.pricing', $product)
            ->with('status', 'Package details saved.');
    }

    public function pricing(Product $product, PackageEditor $editor): Response
    {
        $version = $this->draft($product);

        return Inertia::render('admin/catering/pricing', $this->editorData($editor, $product, $version));
    }

    public function updatePricing(
        SaveMainCateringPricingRequest $request,
        Product $product,
        SavePackageVersion $save
    ): RedirectResponse {
        $version = $this->draft($product);
        $save->handle($request->user(), $product, $request->validated(), $version);

        return redirect()->route('catering.menu', $product)
            ->with('status', 'Pricing and guest quantity saved. Configure the menu next.');
    }

    public function menu(Product $product, PackageEditor $editor): Response
    {
        $version = $this->draft($product);

        return Inertia::render('admin/catering/menu', $this->editorData($editor, $product, $version));
    }

    public function updateMenu(
        SaveMainCateringMenuRequest $request,
        Product $product,
        SavePackageMenuConfiguration $save
    ): RedirectResponse {
        $version = $this->draft($product);
        $save->handle($request->user(), $version, $request->validated());

        return redirect()->route('catering.review', $product)
            ->with('status', 'Menu configuration saved. Review the complete Draft before publishing.');
    }

    public function review(
        Product $product,
        PackageEditor $editor,
        PackageMenuConfiguration $menu,
        PublishedSnapshot $snapshots
    ): Response {
        $version = $this->draft($product);
        Gate::authorize('update', $version);

        $blockingErrors = [];
        $snapshot = null;
        try {
            $menu->validateDraft($version);
            $snapshot = $snapshots->capture($product, $version);
        } catch (ValidationException $exception) {
            $blockingErrors = array_merge(...array_values($exception->errors()));
        }

        return Inertia::render('admin/catering/review', [
            ...$this->editorData($editor, $product, $version),
            'snapshot' => $snapshot,
            'blockingErrors' => $blockingErrors,
        ]);
    }

    private function editorData(PackageEditor $editor, Product $product, PackageVersion $version): array
    {
        $data = $editor->data($product->family, $product);
        $data['version'] = $version->fresh(['foodItems.category', 'requiredCategories']);

        return $data;
    }

    private function draft(Product $product): PackageVersion
    {
        $this->guard($product);

        return $product->versions()->where('status', 'draft')->firstOrFail();
    }

    private function guard(Product $product): void
    {
        $product->loadMissing('family');
        abort_unless($product->family->behavior_type === 'main_package', 404);
        Gate::authorize('update', $product);
        Gate::authorize('price', PackageVersion::class);
    }
}
