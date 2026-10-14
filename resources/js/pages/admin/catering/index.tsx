import type { Collection } from "../../../features/catalog/types";
import { CollectionPresentationForm } from "../../../features/catalog/components/collection-presentation-form";
import { Link } from "@inertiajs/react";

import AdminLayout from "../../../layouts/admin-layout";
import { PackageCards } from "../../../features/packages/components/package-cards";
import type { PackageProduct } from "../../../features/packages/types";
import { PaginationControls } from "../../../components/ui/pagination";

export default function CateringIndex({
    products,
    canManage,
    serviceActive,
    family,
    canManageService,
    canPrice,
    canHistory,
}: {
    products: {
        data: PackageProduct[];
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    canManage: boolean;
    serviceActive: boolean;
    family: Collection;
    canManageService: boolean;
    canPrice: boolean;
    canHistory: boolean;
}) {
    return (
        <AdminLayout
            title="Catering Packages"
            description="Configure your menus, guest requirements and price per pax."
            section="catering"
        >
            {canManage && canPrice && serviceActive ? (
                <Link
                    href={`/admin/packages/editor?service=${family.id}`}
                    className="mb-6 inline-block rounded-lg bg-emerald-800 px-5 py-2.5 font-semibold text-white"
                >
                    Create Package
                </Link>
            ) : (
                <p className="mb-6 rounded-xl bg-amber-50 p-4 text-sm">
                    {!serviceActive
                        ? "Catering is currently disabled. Ask an Administrator to review service availability."
                        : "Creating a package requires editing and pricing permission. Ask an Administrator for access."}
                </p>
            )}
            {products.data.length ? (
                <PackageCards
                    products={products.data}
                    main
                    canManage={canManage}
                    canHistory={canHistory}
                />
            ) : (
                <div className="rounded-2xl border border-dashed bg-white p-12 text-center">
                    <h2 className="text-lg font-semibold">No packages yet</h2>
                    <p className="mt-2 text-sm text-slate-600">
                        Create your first package, then choose its foods and set
                        a price.
                    </p>
                </div>
            )}
            <PaginationControls {...products} />
            {canManageService && (
                <details className="mt-8 rounded-xl border bg-white p-5">
                    <summary className="cursor-pointer text-sm font-semibold">
                        Catering Service Settings
                    </summary>
                    <div className="mt-4 space-y-4">
                        <p className="text-sm">
                            {serviceActive
                                ? "Catering is enabled."
                                : "Catering is disabled. Use Edit to enable it."}
                        </p>
                        <CollectionPresentationForm
                            family={family}
                            canManage={canManageService}
                        />
                    </div>
                </details>
            )}
        </AdminLayout>
    );
}
