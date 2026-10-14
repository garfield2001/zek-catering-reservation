<?php

namespace App\Http\Controllers\Admin\Package;

use App\Actions\Package\AbandonPackageDraft;
use App\Actions\Package\ClonePackageDraft;
use App\Actions\Package\PublishPackageVersion;
use App\Http\Controllers\Controller;
use App\Http\Requests\Package\PublishVersionRequest;
use App\Models\PackageVersion;
use App\Models\Product;
use App\Services\Package\ConfigurationFeasibility;
use App\Services\Package\PackageMenuConfiguration;
use App\Services\Package\PublishedSnapshot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PublicationController extends Controller
{
    public function review(
        Product $product,
        PackageVersion $version,
        ConfigurationFeasibility $feasibility,
        PackageMenuConfiguration $main,
        PublishedSnapshot $snapshots
    ): Response {
        Gate::authorize('publish', PackageVersion::class);
        abort_if($version->status !== 'draft' || $version->package_product_id !== $product->id, 404);
        $product->load('family');
        $errors = [];
        $snapshot = null;
        try {
            if ($version->usesFoodMenu()) {
                $main->validateDraft($version);
            } else {
                $feasibility->find($version);
            }
            $snapshot = $snapshots->capture($product, $version);
        } catch (ValidationException $exception) {
            $errors = array_merge(...array_values($exception->errors()));
        }

        return Inertia::render('admin/packages/publication-review', [
            'product' => $product,
            'version' => $version,
            'snapshot' => $snapshot,
            'blockingErrors' => $errors,
            'previousSnapshot' => Gate::allows('history', PackageVersion::class) ? $product->currentVersion?->published_snapshot : null,
            'warnings' => $snapshot && (int) $version->base_price_minor === 0
                ? ['Base price is ₱0.00. Confirm that this is intentional.'] : [],
        ]);
    }

    public function publish(
        PublishVersionRequest $request,
        Product $product,
        PackageVersion $version,
        PublishPackageVersion $action
    ): RedirectResponse {
        $action->handle($request->user(), $version, (int) $request->validated('draft_revision'));

        return redirect()->route($product->family->behavior_type === 'main_package' ? 'catering.show' : 'packages.show', $product)
            ->with('status', 'Version published. Its commercial configuration is now immutable.');
    }

    public function revise(
        Request $request,
        Product $product,
        PackageVersion $version,
        ClonePackageDraft $action
    ): RedirectResponse {
        Gate::authorize('history', PackageVersion::class);
        $draft = $action->handle($request->user(), $product, $version);

        return redirect()->route($product->family->behavior_type === 'main_package' ? 'catering.show' : 'packages.show', $product)
            ->with('status', 'A new editable draft was cloned from the historical version.');
    }

    public function abandon(
        Request $request,
        Product $product,
        PackageVersion $version,
        AbandonPackageDraft $action
    ): RedirectResponse {
        Gate::authorize('update', $version);
        $data = $request->validate([
            'draft_revision' => ['required', 'integer', 'min:1'],
            'confirm' => ['required', 'accepted'],
        ]);
        $action->handle($request->user(), $version, (int) $data['draft_revision']);

        return redirect()->route($product->family->behavior_type === 'main_package' ? 'catering.show' : 'packages.show', $product)->with('status', 'Draft abandoned. Published history is unchanged.');
    }
}
