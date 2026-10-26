import { Link } from "@inertiajs/react";
import { ArrowLeft, Eye } from "lucide-react";
import { FoodImage } from "../../../components/ui/food-image";
import { formatPesos } from "../../../features/packages/money";
import type { PublicPackage } from "../../../features/storefront/types";
import AdminLayout from "../../../layouts/admin-layout";

export default function StorefrontPreview({
    product,
    offering,
    versionNumber,
}: {
    product: { id: number; family?: { is_addon: boolean } };
    offering: PublicPackage;
    versionNumber: number;
}) {
    return (
        <AdminLayout
            title="Customer Presentation Preview"
            section={product.family?.is_addon ? "services" : "catering"}
            description="Internal-only preview of this unpublished Draft. Customers cannot see this page or its facts."
        >
            <Link
                href={
                    product.family?.is_addon
                        ? `/admin/packages/products/${product.id}`
                        : `/admin/catering-packages/${product.id}`
                }
                className="inline-flex items-center gap-2 text-sm font-semibold text-emerald-800 hover:underline"
            >
                <ArrowLeft size={16} /> Back to package
            </Link>
            <div className="mt-6 rounded-2xl border-2 border-amber-300 bg-amber-50 p-5">
                <div className="flex gap-3">
                    <Eye className="text-amber-800" />
                    <div>
                        <h2 className="font-bold text-amber-950">
                            Draft customer presentation
                        </h2>
                        <p className="mt-1 text-sm text-amber-900">
                            Version {versionNumber} is not public. Pricing and
                            selections shown are for internal review only;
                            publish it before customers can browse it.
                        </p>
                    </div>
                </div>
            </div>
            <section className="mt-7 rounded-2xl border bg-white p-6">
                <p className="text-xs font-bold uppercase tracking-[0.16em] text-amber-700">
                    {offering.family.name}
                </p>
                <h1 className="mt-2 text-3xl font-bold text-emerald-950">
                    {offering.name}
                </h1>
                {offering.description && (
                    <p className="mt-3 max-w-3xl text-stone-600">
                        {offering.description}
                    </p>
                )}
                <p className="mt-5 text-lg font-bold text-emerald-900">
                    {formatPesos(offering.terms.base_price_minor)}{" "}
                    <span className="text-sm font-normal text-stone-600">
                        {offering.terms.price_basis === "per_pax"
                            ? "per guest"
                            : offering.terms.price_basis === "per_pack"
                              ? "per pack"
                              : "per set"}
                    </span>
                </p>
                {offering.menu && (
                    <div className="mt-7">
                        <h2 className="text-xl font-bold text-emerald-950">
                            Menu presentation
                        </h2>
                        <p className="mt-1 text-sm text-stone-600">
                            Customer chooses{" "}
                            {offering.menu.total_selection_count} Food Items.
                        </p>
                        <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            {offering.menu.food_items.map((food) => (
                                <div
                                    className="flex items-center gap-3 rounded-xl border p-3"
                                    key={food.id}
                                >
                                    <FoodImage
                                        compact
                                        name={food.name}
                                        src={food.thumbnail_url}
                                    />
                                    <span>
                                        <strong className="block">
                                            {food.name}
                                        </strong>
                                        <small className="text-stone-500">
                                            {food.is_fixed
                                                ? "Included Item"
                                                : food.category_name}
                                        </small>
                                    </span>
                                </div>
                            ))}
                        </div>
                    </div>
                )}
                {!offering.menu && (
                    <div className="mt-7 space-y-5">
                        {offering.groups.map((group) => (
                            <div key={group.group_code}>
                                <h2 className="text-xl font-bold text-emerald-950">
                                    {group.name}
                                </h2>
                                <p className="text-sm text-stone-600">
                                    {group.mode === "fixed"
                                        ? "Included Item"
                                        : `Customer Choice: ${group.minimum_count} to ${group.maximum_count}`}
                                </p>
                                <ul className="mt-2 list-disc pl-5 text-sm text-stone-700">
                                    {group.options.map((option) => (
                                        <li key={option.offering_option_id}>
                                            {option.food_item.name} —{" "}
                                            {option.serving_option.label}
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        ))}
                    </div>
                )}
            </section>
        </AdminLayout>
    );
}
