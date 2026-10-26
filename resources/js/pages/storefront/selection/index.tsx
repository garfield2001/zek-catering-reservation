import { Link, router } from "@inertiajs/react";
import { ArrowRight, Trash2 } from "lucide-react";
import { formatPesos } from "../../../features/packages/money";
import type {
    PublicListing,
    PublicPackage,
} from "../../../features/storefront/types";
import StorefrontLayout from "../../../layouts/storefront-layout";

type Item = {
    key: string;
    type?: "package" | "listing";
    offering?: PublicPackage | PublicListing;
    preview?: { quantity: number; total_minor: string };
    invalid?: boolean;
    message?: string;
};

export default function SelectionIndex({
    items,
    totalMinor,
    selectionCount,
}: {
    items: Item[];
    totalMinor: string;
    selectionCount: number;
}) {
    return (
        <StorefrontLayout title="My Selection" selectionCount={selectionCount}>
            <div className="mx-auto max-w-4xl px-4 py-10 sm:px-6">
                <p className="text-xs font-bold uppercase tracking-[0.16em] text-amber-700">
                    Planning selection
                </p>
                <h1 className="mt-2 font-serif text-4xl font-bold text-emerald-950">
                    My Selection
                </h1>
                <p className="mt-4 max-w-2xl leading-7 text-stone-600">
                    Review the packages and items you have selected in this
                    browser. This is not a reservation, quote, or order.
                </p>
                {items.length === 0 ? (
                    <div className="mt-8 rounded-2xl border border-dashed border-stone-300 bg-white p-8">
                        <h2 className="text-xl font-bold text-emerald-950">
                            Your selection is empty
                        </h2>
                        <p className="mt-2 text-stone-600">
                            Start by exploring our currently available catering
                            packages or add-on services.
                        </p>
                        <div className="mt-5 flex flex-wrap gap-3">
                            <Link
                                href="/catering-packages"
                                className="rounded-xl bg-emerald-800 px-4 py-3 text-sm font-semibold text-white"
                            >
                                Catering Packages
                            </Link>
                            <Link
                                href="/add-on-services"
                                className="rounded-xl border border-emerald-800 px-4 py-3 text-sm font-semibold text-emerald-900"
                            >
                                Add-on Services
                            </Link>
                        </div>
                    </div>
                ) : (
                    <>
                        <div className="mt-8 space-y-4">
                            {items.map((item) =>
                                item.invalid ? (
                                    <article
                                        key={item.key}
                                        className="rounded-2xl border border-amber-300 bg-amber-50 p-5"
                                    >
                                        <h2 className="font-bold text-amber-950">
                                            An item needs review
                                        </h2>
                                        <p className="mt-1 text-sm text-amber-900">
                                            {item.message}
                                        </p>
                                        <button
                                            type="button"
                                            className="mt-3 text-sm font-semibold text-amber-950 underline"
                                            onClick={() =>
                                                router.delete(
                                                    `/my-selection/${item.key}`,
                                                )
                                            }
                                        >
                                            Remove unavailable item
                                        </button>
                                    </article>
                                ) : (
                                    <article
                                        key={item.key}
                                        className="flex flex-col gap-4 rounded-2xl border border-stone-200 bg-white p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between"
                                    >
                                        <div>
                                            <p className="text-xs font-bold uppercase tracking-[0.14em] text-amber-700">
                                                {item.type === "package"
                                                    ? "Package"
                                                    : "Individual item"}
                                            </p>
                                            <h2 className="mt-1 text-lg font-bold text-emerald-950">
                                                {item.offering?.name}
                                            </h2>
                                            <p className="mt-1 text-sm text-stone-600">
                                                Quantity:{" "}
                                                {item.preview?.quantity} ·
                                                Estimate:{" "}
                                                <strong>
                                                    {item.preview &&
                                                        formatPesos(
                                                            item.preview
                                                                .total_minor,
                                                        )}
                                                </strong>
                                            </p>
                                        </div>
                                        <div className="flex gap-3">
                                            <Link
                                                href={
                                                    item.type === "package"
                                                        ? `/catering-packages/${item.offering?.id}`
                                                        : "/add-on-services"
                                                }
                                                className="rounded-lg border border-emerald-800 px-3 py-2 text-sm font-semibold text-emerald-900"
                                            >
                                                Adjust
                                            </Link>
                                            <button
                                                type="button"
                                                aria-label={`Remove ${item.offering?.name}`}
                                                onClick={() =>
                                                    router.delete(
                                                        `/my-selection/${item.key}`,
                                                    )
                                                }
                                                className="rounded-lg border border-red-200 px-3 py-2 text-red-700 hover:bg-red-50"
                                            >
                                                <Trash2 size={18} />
                                            </button>
                                        </div>
                                    </article>
                                ),
                            )}
                        </div>
                        <div className="mt-6 rounded-2xl bg-emerald-950 p-6 text-white">
                            <p className="text-sm text-emerald-100">
                                Current planning total
                            </p>
                            <strong className="mt-1 block text-3xl">
                                {formatPesos(totalMinor)}
                            </strong>
                            <p className="mt-2 text-sm leading-6 text-emerald-100">
                                This estimate uses currently published pricing
                                and may change if an offering becomes
                                unavailable. Final event details are confirmed
                                with our team.
                            </p>
                        </div>
                        <Link
                            href="/catering-packages"
                            className="mt-6 inline-flex items-center gap-2 text-sm font-semibold text-emerald-800 hover:underline"
                        >
                            Continue browsing <ArrowRight size={16} />
                        </Link>
                    </>
                )}
            </div>
        </StorefrontLayout>
    );
}
