import { Link, router } from "@inertiajs/react";
import { Check, Copy, RotateCcw } from "lucide-react";
import { useState } from "react";
import StorefrontLayout from "../../../layouts/storefront-layout";

export default function ReservationRequestSubmitted({
    reference,
    accessCode,
    selectionCount,
}: {
    reference: string;
    accessCode: string | null;
    selectionCount: number;
}) {
    const [copied, setCopied] = useState<"reference" | "access" | null>(null);
    const [copyFailed, setCopyFailed] = useState(false);

    async function copyReference() {
        try {
            await navigator.clipboard.writeText(reference);
            setCopied("reference");
            setCopyFailed(false);
            window.setTimeout(() => setCopied(null), 2500);
        } catch {
            setCopyFailed(true);
        }
    }

    async function copyAccessCode() {
        if (!accessCode) return;
        try {
            await navigator.clipboard.writeText(accessCode);
            setCopied("access");
            setCopyFailed(false);
            window.setTimeout(() => setCopied(null), 2500);
        } catch {
            setCopyFailed(true);
        }
    }

    return (
        <StorefrontLayout
            title="Request received"
            selectionCount={selectionCount}
        >
            <div className="mx-auto max-w-2xl px-4 py-16 text-center sm:px-6">
                <p className="text-xs font-bold uppercase tracking-[0.16em] text-amber-700">
                    Request received
                </p>
                <h1 className="mt-3 font-serif text-4xl font-bold text-emerald-950">
                    Thank you for your inquiry
                </h1>
                <p className="mt-5 text-lg text-stone-600">
                    Keep this reference code for your records.
                </p>
                <div className="mt-5 rounded-2xl border-2 border-emerald-800 bg-emerald-50 p-5">
                    <p className="text-xs font-bold uppercase tracking-[0.16em] text-emerald-800">
                        Inquiry reference code
                    </p>
                    <output className="mt-2 block break-all font-mono text-2xl font-bold tracking-wide text-emerald-950">
                        {reference}
                    </output>
                    <button
                        type="button"
                        onClick={() => void copyReference()}
                        className="mt-4 inline-flex items-center gap-2 rounded-xl bg-emerald-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-900"
                    >
                        {copied === "reference" ? (
                            <Check size={16} aria-hidden="true" />
                        ) : (
                            <Copy size={16} aria-hidden="true" />
                        )}
                        {copied === "reference"
                            ? "Copied!"
                            : "Copy Reference Code"}
                    </button>
                    <p
                        role="status"
                        aria-live="polite"
                        className="mt-3 text-sm text-emerald-950"
                    >
                        {copyFailed
                            ? "We could not copy the code. Please select and copy it manually."
                            : copied === "reference"
                              ? "Reference code copied."
                              : ""}
                    </p>
                </div>
                {accessCode && (
                    <div className="mt-4 rounded-2xl border-2 border-amber-500 bg-amber-50 p-5">
                        <p className="text-xs font-bold uppercase tracking-[0.16em] text-amber-900">
                            Customer access code
                        </p>
                        <output className="mt-2 block break-all font-mono text-2xl font-bold tracking-wide text-emerald-950">
                            {accessCode}
                        </output>
                        <button
                            type="button"
                            onClick={() => void copyAccessCode()}
                            className="mt-4 inline-flex items-center gap-2 rounded-xl bg-emerald-800 px-4 py-2.5 text-sm font-semibold text-white"
                        >
                            {copied === "access" ? (
                                <Check size={16} aria-hidden="true" />
                            ) : (
                                <Copy size={16} aria-hidden="true" />
                            )}{" "}
                            {copied === "access"
                                ? "Copied!"
                                : "Copy Access Code"}
                        </button>
                        <p className="mt-3 text-sm text-amber-950">
                            Save this private code. You will need it with your
                            reference and mobile number to track your request.
                        </p>
                        {copyFailed && (
                            <p
                                role="status"
                                className="mt-2 text-sm text-rose-700"
                            >
                                We could not copy the code. Please select and
                                copy it manually.
                            </p>
                        )}
                    </div>
                )}
                <p className="mt-6 leading-7 text-stone-600">
                    Our Staff will review your tentative event details and
                    current package selection. Submitting this request does not
                    guarantee availability, reserve a date, create a confirmed
                    reservation, or create a final quotation.
                </p>
                <div className="mt-8 flex flex-wrap justify-center gap-4">
                    <Link
                        href="/"
                        className="inline-block rounded-xl bg-emerald-800 px-5 py-3 text-sm font-semibold text-white"
                    >
                        Return to ZEK Catering
                    </Link>
                    <button
                        type="button"
                        onClick={() => router.delete("/inquiry")}
                        className="inline-flex items-center gap-2 rounded-xl border border-emerald-800 px-5 py-3 text-sm font-semibold text-emerald-900"
                    >
                        <RotateCcw size={16} /> Start New Inquiry
                    </button>
                </div>
            </div>
        </StorefrontLayout>
    );
}
