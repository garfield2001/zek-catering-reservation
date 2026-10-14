import { Link } from "@inertiajs/react";
import { Pencil } from "lucide-react";
import { useState } from "react";
import { PackageDetails } from "./package-details";
import { StatusBadge } from "../../catalog/components/status-badge";
import type { PackageProduct, PackageVersion } from "../types";
import { formatPesos as formatMoney } from "../money";

export function PackageCards({
    products,
    main = false,
    canManage = false,
    canHistory = false,
}: {
    products: (PackageProduct & { current_version?: PackageVersion | null })[];
    main?: boolean;
    canManage?: boolean;
    canHistory?: boolean;
}) {
    const [details, setDetails] = useState<number | null>(null);
    return (
        <div className="grid gap-4 md:grid-cols-2">
            {details !== null && (
                <PackageDetails
                    productId={details}
                    open
                    onClose={() => setDetails(null)}
                />
            )}
            {products.map((product) => {
                const draft = product.versions?.find(
                    (v) => v.status === "draft",
                );
                const version = draft ?? product.current_version;
                return (
                    <article
                        key={product.id}
                        className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
                    >
                        <div className="flex justify-between gap-3">
                            <h2 className="text-lg font-semibold">
                                {product.name}
                            </h2>
                            <span className="shrink-0 text-sm text-slate-600">
                                {product.archived_at ? (
                                    "Archived"
                                ) : (
                                    <StatusBadge active={product.active} />
                                )}
                            </span>
                        </div>
                        <p className="mt-1 text-sm text-emerald-800">
                            {draft
                                ? "Package Draft"
                                : product.current_version
                                  ? "Published"
                                  : "No publication"}
                            {draft && product.current_version
                                ? " · Published version unchanged"
                                : ""}
                        </p>
                        {version && (
                            <dl className="mt-4 grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <dt className="text-slate-500">
                                        {draft
                                            ? "Draft price"
                                            : "Published price"}
                                    </dt>
                                    <dd className="font-semibold">
                                        {formatMoney(
                                            String(version.base_price_minor),
                                        )}{" "}
                                        /{" "}
                                        {main
                                            ? "pax"
                                            : version.price_basis === "per_pack"
                                              ? "pack"
                                              : "set"}
                                    </dd>
                                </div>
                                {main && (
                                    <div>
                                        <dt className="text-slate-500">
                                            Food selections
                                        </dt>
                                        <dd className="font-semibold">
                                            {version.total_selection_count ??
                                                "Not set"}
                                        </dd>
                                    </div>
                                )}
                                <div>
                                    <dt className="text-slate-500">
                                        {main
                                            ? "Minimum guests"
                                            : "Minimum quantity"}
                                    </dt>
                                    <dd>{version.minimum_quantity}</dd>
                                </div>
                            </dl>
                        )}
                        <div className="mt-5 flex flex-wrap items-center gap-4">
                            {canManage &&
                                !product.archived_at &&
                                (draft ||
                                    !product.current_version ||
                                    canHistory) && (
                                    <Link
                                        className="inline-flex items-center gap-2 rounded-lg bg-emerald-800 px-4 py-2 text-sm font-semibold text-white"
                                        href={`/admin/packages/products/${product.id}/edit`}
                                    >
                                        <Pencil size={16} aria-hidden="true" />
                                        {draft &&
                                        !product.current_version_id &&
                                        !draft.total_selection_count
                                            ? "Continue Setup"
                                            : "Edit"}
                                    </Link>
                                )}
                            {canManage &&
                                !product.archived_at &&
                                !draft &&
                                product.current_version &&
                                !canHistory && (
                                    <p className="text-sm text-slate-600">
                                        Editing a published package requires
                                        history access to create a safe Draft.
                                        Ask an Administrator for access.
                                    </p>
                                )}
                            <button
                                type="button"
                                className="text-sm text-emerald-800 underline"
                                onClick={() => setDetails(product.id)}
                            >
                                View Details
                            </button>
                            <Link
                                className="text-sm text-stone-600 underline"
                                href={
                                    main
                                        ? `/admin/catering-packages/${product.id}`
                                        : `/admin/packages/products/${product.id}`
                                }
                            >
                                Publication & History
                            </Link>
                        </div>
                    </article>
                );
            })}
        </div>
    );
}
