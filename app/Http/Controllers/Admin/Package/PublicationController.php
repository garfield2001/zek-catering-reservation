<?php

namespace App\Http\Controllers\Admin\Package;

use App\Actions\Package\AbandonVariantDraft;
use App\Actions\Package\CloneVariantDraft;
use App\Actions\Package\PublishVariantVersion;
use App\Http\Controllers\Controller;
use App\Http\Requests\Package\PublishVersionRequest;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VariantVersion;
use App\Services\Package\ConfigurationFeasibility;
use App\Services\Package\PublishedSnapshot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PublicationController extends Controller
{
    public function review(Product $product, ProductVariant $variant, VariantVersion $version,
        ConfigurationFeasibility $feasibility, PublishedSnapshot $snapshots): Response
    {
        Gate::authorize('publish', VariantVersion::class);
        abort_if($version->status !== 'draft', 404);
        $errors = [];
        $snapshot = null;
        try {
            $feasibility->find($version);
            $snapshot = $snapshots->capture($product, $variant, $version);
        } catch (ValidationException $exception) {
            $errors = array_merge(...array_values($exception->errors()));
        }

        return Inertia::render('admin/packages/publication-review', [
            'product' => $product, 'variant' => $variant, 'version' => $version,
            'snapshot' => $snapshot, 'blockingErrors' => $errors,
            'previousSnapshot' => $variant->currentVersion?->published_snapshot,
            'warnings' => $snapshot && (int) $version->base_price_minor === 0
                ? ['Base price is ₱0.00. Confirm that this is intentional.'] : [],
        ]);
    }

    public function publish(PublishVersionRequest $request, Product $product, ProductVariant $variant,
        VariantVersion $version, PublishVariantVersion $action): RedirectResponse
    {
        $action->handle($request->user(), $version, (int) $request->validated('draft_revision'));

        return redirect()->route('packages.versions.show', [$product, $variant, $version])
            ->with('status', 'Version published. Its commercial configuration is now immutable.');
    }

    public function revise(Request $request, Product $product, ProductVariant $variant,
        VariantVersion $version, CloneVariantDraft $action): RedirectResponse
    {
        Gate::authorize('history', VariantVersion::class);
        $draft = $action->handle($request->user(), $variant, $version);

        return redirect()->route('packages.versions.show', [$product, $variant, $draft])
            ->with('status', 'A new editable draft was cloned from the historical version.');
    }

    public function abandon(Request $request, Product $product, ProductVariant $variant,
        VariantVersion $version, AbandonVariantDraft $action): RedirectResponse
    {
        Gate::authorize('update', $version);
        $data = $request->validate(['draft_revision' => ['required', 'integer', 'min:1'],
            'confirm' => ['required', 'accepted']]);
        $action->handle($request->user(), $version, (int) $data['draft_revision']);

        return redirect()->route('packages.show', $product)->with('status', 'Draft abandoned. Published history is unchanged.');
    }
}
