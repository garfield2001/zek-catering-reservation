import { Link, router } from "@inertiajs/react";
import { ArrowRight, RotateCcw, Trash2 } from "lucide-react";
import { useState } from "react";
import { ConfirmDialog } from "../../../components/ui/confirm-dialog";
import { formatPesos } from "../../../features/packages/money";
import type {
    PublicListing,
    PublicPackage,
} from "../../../features/storefront/types";
import StorefrontLayout from "../../../layouts/storefront-layout";

type Item = {
    key: string;
    type?: "package" | "listing";
    inquiry_role?: "main" | "addon";
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
    const [confirmStartNew, setConfirmStartNew] = useState(false);

    return (
        <StorefrontLayout
            title="Review your inquiry"
            selectionCount={selectionCount}
        >
            <div className="mx-auto max-w-4xl px-4 py-10 sm:px-6">
                <p className="text-xs font-bold uppercase tracking-[0.16em] text-amber-700">
                    Event inquiry
                </p>
                <h1 className="mt-2 font-serif text-4xl font-bold text-emerald-950">
                    Review your inquiry
                </h1>
                <p className="mt-4 max-w-2xl leading-7 text-stone-600">
                    Step 4 of 6: review your Main Catering package, optional
                    services, and Laravel-calculated planning estimate before
                    entering event details.
                </p>
                {items.length === 0 ? (
                    <div className="mt-8 rounded-2xl border border-dashed border-stone-300 bg-white p-8">
                        <h2 className="text-xl font-bold text-emerald-950">
                            Start with a Main Catering package
                        </h2>
                        <p className="mt-2 text-stone-600">
                            A public inquiry needs one Main Catering package.
                            Optional add-ons can be added after your main
                            package is configured.
                        </p>
                        <Link
                            href="/catering-packages"
                            className="mt-5 inline-flex rounded-xl bg-emerald-800 px-4 py-3 text-sm font-semibold text-white"
                        >
                            Choose Main Catering
                        </Link>
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
                                            An inquiry item needs review
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
                                                {item.inquiry_role === "main"
                                                    ? "Main Catering package"
                                                    : item.type === "package"
                                                      ? "Optional service package"
                                                      : "Optional individual item"}
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
                                                    item.type === "package" &&
                                                    item.inquiry_role === "main"
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
                                Laravel-calculated planning estimate
                            </p>
                            <strong className="mt-1 block text-3xl">
                                {formatPesos(totalMinor)}
                            </strong>
                            <p className="mt-2 text-sm leading-6 text-emerald-100">
                                The estimate is revalidated from current
                                published terms when you submit. It is not a
                                quotation, availability guarantee, or date hold.
                            </p>
                        </div>
                        <div className="mt-6 flex flex-wrap items-center gap-4">
                            <Link
                                href="/reservation-request"
                                className="inline-flex items-center gap-2 rounded-xl bg-emerald-800 px-4 py-3 text-sm font-semibold text-white"
                            >
                                Continue to event details{" "}
                                <ArrowRight size={16} />
                            </Link>
                            <Link
                                href="/add-on-services"
                                className="text-sm font-semibold text-emerald-800 underline"
                            >
                                Choose optional add-ons
                            </Link>
                            <button
                                type="button"
                                onClick={() => setConfirmStartNew(true)}
                                className="inline-flex items-center gap-2 text-sm font-semibold text-stone-700 underline"
                            >
                                <RotateCcw size={16} /> Start new inquiry
                            </button>
                        </div>
                    </>
                )}
            </div>
            <ConfirmDialog
                open={confirmStartNew}
                onClose={() => setConfirmStartNew(false)}
                title="Start a new inquiry?"
                description="Your unfinished package and add-on choices in this browser will be cleared. Submitted inquiries are not changed."
                confirmLabel="Start new inquiry"
                destructive
                onConfirm={() => router.delete("/inquiry")}
            />
        </StorefrontLayout>
    );
}
