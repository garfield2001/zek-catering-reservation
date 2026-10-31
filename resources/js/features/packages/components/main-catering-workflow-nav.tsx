import type { ReactNode } from "react";
import { Link } from "@inertiajs/react";
import type { Collection } from "../../catalog/types";
import type { PackageProduct } from "../types";

const sections = [
    ["details", "Package Details"],
    ["pricing", "Pricing & Quantity"],
    ["menu", "Menu Configuration"],
    ["review", "Review & Save"],
] as const;

export function MainCateringWorkflowPage({
    family,
    product,
    current,
    title,
    description,
    children,
}: {
    family: Collection;
    product: PackageProduct | null;
    current: (typeof sections)[number][0];
    title: string;
    description: string;
    children: ReactNode;
}) {
    const workspace = product
        ? `/admin/catering-packages/${product.id}`
        : "/admin/catering-packages";

    return (
        <div className="space-y-6">
            <nav aria-label="Breadcrumb" className="text-sm text-slate-600">
                <Link className="text-emerald-800 underline" href={workspace}>
                    {product ? "Back to Package" : "Back to Catering Packages"}
                </Link>
                <span> / {title}</span>
            </nav>
            <header>
                <p className="text-sm font-medium text-emerald-800">
                    {family.name}
                </p>
                <h1 className="mt-1 text-2xl font-semibold">{title}</h1>
                <p className="mt-1 text-slate-600">{description}</p>
            </header>
            {product && (
                <nav
                    aria-label="Package configuration pages"
                    className="flex flex-wrap gap-x-5 gap-y-2 border-b pb-3 text-sm"
                >
                    {sections.map(([key, label]) => (
                        <Link
                            key={key}
                            href={`/admin/catering-packages/${product.id}/${key}`}
                            aria-current={current === key ? "page" : undefined}
                            className={
                                current === key
                                    ? "font-semibold text-emerald-800"
                                    : "text-slate-600 underline"
                            }
                        >
                            {label}
                        </Link>
                    ))}
                </nav>
            )}
            {children}
        </div>
    );
}
