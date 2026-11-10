import { Head, Link, router, useForm } from "@inertiajs/react";
import { useState } from "react";
import AdminLayout from "../../../layouts/admin-layout";
import { formatPesos } from "../../../features/packages/money";
import { VerifyPayment } from "./verify-payment";

export default function PaymentsIndex({
    payments,
    methods,
    quotations,
    acceptedVersions,
    canRecord,
    canVerify,
    canRecordEarlyException,
}: {
    payments: {
        data: Array<{
            id: number;
            quotation_id: number | null;
            status: string;
            amount_minor: string | null;
            paid_at: string | null;
            method: { name: string } | null;
            quotation: { reference: string } | null;
            reservation_request: { reference: string } | null;
            transaction_reference: string | null;
            has_proof: boolean;
            evidence_versions: Array<{
                id: number;
                version_number: number;
                submitted_at: string;
            }>;
            allocation_history: Array<{
                quotation_version: { version_number: number };
                superseded_at: string | null;
            }>;
        }>;
        links: unknown;
    };
    methods: Array<{ id: number; name: string; is_offline: boolean }>;
    quotations: Array<{ id: number; reference: string }>;
    acceptedVersions: Array<{
        id: number;
        quotation_id: number;
        version_number: number;
        quotation: { reference: string };
    }>;
    canRecord: boolean;
    canVerify: boolean;
    canRecordEarlyException: boolean;
}) {
    const [recordEarlyException, setRecordEarlyException] = useState(false);
    const form = useForm({
        quotation_id: "",
        payment_method_id: "",
        amount: "",
        paid_at: "",
        transaction_reference: "",
        notes: "",
        exception_reason: "",
        idempotency_key: crypto.randomUUID(),
        proof: null as File | null,
    });
    const recordFieldNames = [
        "quotation_id",
        "payment_method_id",
        "amount",
        "paid_at",
        "transaction_reference",
        "proof",
        "notes",
        "exception_reason",
    ];
    return (
        <AdminLayout title="Payments" section="quotations">
            <Head title="Payments" />
            <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
                <p className="text-sm text-stone-600">
                    Review submitted proof, record offline receipts, and keep
                    financial history traceable.
                </p>
                <Link
                    className="rounded-lg border px-3 py-2 text-sm font-semibold"
                    href="/admin/business-policies"
                >
                    Customer payment instructions
                </Link>
            </div>
            {canRecord && (
                <form
                    encType="multipart/form-data"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post("/admin/payments", { forceFormData: true });
                    }}
                    className="mb-5 grid gap-3 rounded-xl border bg-white p-5 sm:grid-cols-2"
                >
                    <h2 className="sm:col-span-2 font-semibold">
                        Record cash or offline payment
                    </h2>
                    {canRecordEarlyException && (
                        <label className="sm:col-span-2 flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                checked={recordEarlyException}
                                onChange={(event) => {
                                    setRecordEarlyException(
                                        event.target.checked,
                                    );
                                    if (event.target.checked)
                                        form.setData("quotation_id", "");
                                    form.clearErrors(
                                        "quotation_id",
                                        "exception_reason",
                                    );
                                }}
                            />
                            Record an early Administrator exception before a
                            quotation is accepted
                        </label>
                    )}
                    <label>
                        Accepted quotation
                        <select
                            required={!recordEarlyException}
                            disabled={recordEarlyException}
                            value={form.data.quotation_id}
                            onChange={(event) => (
                                form.setData(
                                    "quotation_id",
                                    event.target.value,
                                ),
                                form.clearErrors("quotation_id")
                            )}
                            aria-invalid={Boolean(form.errors.quotation_id)}
                            className={`mt-1 w-full ${form.errors.quotation_id ? "border-rose-600" : ""}`}
                        >
                            <option value="">Select quotation</option>
                            {quotations.map((quotation) => (
                                <option key={quotation.id} value={quotation.id}>
                                    {quotation.reference}
                                </option>
                            ))}
                        </select>
                        <FormError error={form.errors.quotation_id} />
                    </label>
                    <label>
                        Payment method
                        <select
                            required
                            value={form.data.payment_method_id}
                            onChange={(event) => (
                                form.setData(
                                    "payment_method_id",
                                    event.target.value,
                                ),
                                form.clearErrors("payment_method_id")
                            )}
                            aria-invalid={Boolean(
                                form.errors.payment_method_id,
                            )}
                            className={`mt-1 w-full ${form.errors.payment_method_id ? "border-rose-600" : ""}`}
                        >
                            <option value="">Select method</option>
                            {methods.map((method) => (
                                <option key={method.id} value={method.id}>
                                    {method.name}
                                    {method.is_offline ? " (offline)" : ""}
                                </option>
                            ))}
                        </select>
                        <FormError error={form.errors.payment_method_id} />
                    </label>
                    <label>
                        Amount (PHP)
                        <input
                            required
                            type="number"
                            min="0.01"
                            step="0.01"
                            value={form.data.amount}
                            onChange={(event) => (
                                form.setData("amount", event.target.value),
                                form.clearErrors("amount")
                            )}
                            aria-invalid={Boolean(form.errors.amount)}
                            className={`mt-1 w-full ${form.errors.amount ? "border-rose-600" : ""}`}
                        />
                        <FormError error={form.errors.amount} />
                    </label>
                    <label>
                        Received at
                        <input
                            required
                            type="datetime-local"
                            value={form.data.paid_at}
                            onChange={(event) => (
                                form.setData("paid_at", event.target.value),
                                form.clearErrors("paid_at")
                            )}
                            aria-invalid={Boolean(form.errors.paid_at)}
                            className={`mt-1 w-full ${form.errors.paid_at ? "border-rose-600" : ""}`}
                        />
                        <FormError error={form.errors.paid_at} />
                    </label>
                    <label>
                        Reference (optional)
                        <input
                            value={form.data.transaction_reference}
                            onChange={(event) => (
                                form.setData(
                                    "transaction_reference",
                                    event.target.value,
                                ),
                                form.clearErrors("transaction_reference")
                            )}
                            aria-invalid={Boolean(
                                form.errors.transaction_reference,
                            )}
                            className={`mt-1 w-full ${form.errors.transaction_reference ? "border-rose-600" : ""}`}
                        />
                        <FormError error={form.errors.transaction_reference} />
                    </label>
                    <label>
                        Evidence (optional)
                        <input
                            type="file"
                            accept="application/pdf,image/jpeg,image/png"
                            onChange={(event) => (
                                form.setData(
                                    "proof",
                                    event.target.files?.[0] ?? null,
                                ),
                                form.clearErrors("proof")
                            )}
                            aria-invalid={Boolean(form.errors.proof)}
                            className={`mt-1 w-full ${form.errors.proof ? "border-rose-600" : ""}`}
                        />
                        <FormError error={form.errors.proof} />
                    </label>
                    <label className="sm:col-span-2">
                        Notes
                        <textarea
                            value={form.data.notes}
                            onChange={(event) => (
                                form.setData("notes", event.target.value),
                                form.clearErrors("notes")
                            )}
                            aria-invalid={Boolean(form.errors.notes)}
                            className={`mt-1 w-full ${form.errors.notes ? "border-rose-600" : ""}`}
                        />
                        <FormError error={form.errors.notes} />
                    </label>
                    {recordEarlyException && (
                        <label className="sm:col-span-2">
                            Reason for early exception
                            <textarea
                                required
                                value={form.data.exception_reason}
                                onChange={(event) => (
                                    form.setData(
                                        "exception_reason",
                                        event.target.value,
                                    ),
                                    form.clearErrors("exception_reason")
                                )}
                                aria-invalid={Boolean(
                                    form.errors.exception_reason,
                                )}
                                className={`mt-1 w-full ${form.errors.exception_reason ? "border-rose-600" : ""}`}
                            />
                            {form.errors.exception_reason && (
                                <span
                                    role="alert"
                                    className="mt-1 block text-sm text-rose-700"
                                >
                                    {form.errors.exception_reason}
                                </span>
                            )}
                        </label>
                    )}
                    {Object.entries(form.errors)
                        .filter(([field]) => !recordFieldNames.includes(field))
                        .map(([field, error]) => (
                            <p
                                key={field}
                                role="alert"
                                className="sm:col-span-2 text-sm text-rose-700"
                            >
                                {error}
                            </p>
                        ))}
                    <button className="w-fit rounded bg-emerald-800 px-4 py-2 text-sm font-semibold text-white">
                        Record payment
                    </button>
                </form>
            )}
            <div className="overflow-x-auto rounded-xl border bg-white">
                <table className="min-w-full text-sm">
                    <thead>
                        <tr className="border-b text-left">
                            <th className="p-3">Payment</th>
                            <th>Customer transaction</th>
                            <th>Method</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th className="p-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {payments.data.map((payment) => (
                            <tr key={payment.id} className="border-b">
                                <td className="p-3">
                                    #{payment.id}
                                    <br />
                                    <span className="text-stone-500">
                                        {payment.paid_at
                                            ? new Date(
                                                  payment.paid_at,
                                              ).toLocaleString("en-PH", {
                                                  timeZone: "Asia/Manila",
                                              })
                                            : "Submitted receipt"}
                                    </span>
                                </td>
                                <td>
                                    {payment.quotation?.reference ??
                                        payment.reservation_request
                                            ?.reference ??
                                        "Exceptional / unallocated"}
                                    <br />
                                    {payment.transaction_reference ??
                                        "No reference"}
                                    {payment.allocation_history.length > 0 && (
                                        <span className="block text-xs text-stone-500">
                                            {payment.allocation_history
                                                .map(
                                                    (allocation) =>
                                                        `Version ${allocation.quotation_version.version_number}${allocation.superseded_at ? " (superseded)" : " (active)"}`,
                                                )
                                                .join(", ")}
                                        </span>
                                    )}
                                </td>
                                <td>
                                    {payment.method?.name ?? "Customer receipt"}
                                </td>
                                <td>
                                    {payment.amount_minor
                                        ? formatPesos(payment.amount_minor)
                                        : "Awaiting verification"}
                                </td>
                                <td>
                                    <span className="rounded-full bg-stone-100 px-2 py-1 text-xs font-semibold">
                                        {payment.status.replaceAll("_", " ")}
                                    </span>
                                </td>
                                <td className="p-3">
                                    {payment.evidence_versions.length > 0 && (
                                        <div className="mb-2 flex flex-wrap gap-2">
                                            {payment.evidence_versions.map(
                                                (evidence) => (
                                                    <span
                                                        key={evidence.id}
                                                        className="flex gap-1"
                                                    >
                                                        <a
                                                            href={`/admin/payments/${payment.id}/evidence/${evidence.id}/preview`}
                                                            target="_blank"
                                                            rel="noreferrer"
                                                            className="rounded border px-2 py-1 text-xs"
                                                        >
                                                            Preview v
                                                            {
                                                                evidence.version_number
                                                            }
                                                        </a>
                                                        <a
                                                            href={`/admin/payments/${payment.id}/evidence/${evidence.id}`}
                                                            className="rounded border px-2 py-1 text-xs"
                                                        >
                                                            Download
                                                        </a>
                                                    </span>
                                                ),
                                            )}
                                        </div>
                                    )}
                                    {payment.has_proof &&
                                        payment.evidence_versions.length ===
                                            0 && (
                                            <div className="mb-2 flex gap-2">
                                                <a
                                                    href={`/admin/payments/${payment.id}/evidence/preview`}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="rounded border px-2 py-1 text-xs"
                                                >
                                                    Preview receipt
                                                </a>
                                                <a
                                                    href={`/admin/payments/${payment.id}/evidence`}
                                                    className="rounded border px-2 py-1 text-xs"
                                                >
                                                    Download receipt
                                                </a>
                                            </div>
                                        )}
                                    {canVerify &&
                                        payment.status ===
                                            "pending_verification" && (
                                            <div className="flex gap-2">
                                                <VerifyPayment
                                                    paymentId={payment.id}
                                                    quotationId={
                                                        payment.quotation_id
                                                    }
                                                    acceptedVersions={
                                                        acceptedVersions
                                                    }
                                                />
                                                <button
                                                    className="rounded border px-2 py-1 text-xs"
                                                    onClick={() => {
                                                        const reason =
                                                            window.prompt(
                                                                "Reason for rejection",
                                                            );
                                                        if (reason)
                                                            router.post(
                                                                `/admin/payments/${payment.id}/review`,
                                                                {
                                                                    action: "reject",
                                                                    reason,
                                                                },
                                                            );
                                                    }}
                                                >
                                                    Reject
                                                </button>
                                            </div>
                                        )}
                                    {canVerify &&
                                        payment.status === "verified" && (
                                            <div className="flex flex-wrap gap-2">
                                                <button
                                                    className="rounded border px-2 py-1 text-xs"
                                                    onClick={() => {
                                                        const reason =
                                                            window.prompt(
                                                                "Reason for reversal",
                                                            );
                                                        if (reason)
                                                            router.post(
                                                                `/admin/payments/${payment.id}/review`,
                                                                {
                                                                    action: "reverse",
                                                                    reason,
                                                                },
                                                            );
                                                    }}
                                                >
                                                    Reverse
                                                </button>
                                                {payment.quotation === null && (
                                                    <EarlyPaymentAllocation
                                                        paymentId={payment.id}
                                                        versions={
                                                            acceptedVersions
                                                        }
                                                    />
                                                )}
                                            </div>
                                        )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                {payments.data.length === 0 && (
                    <p className="p-6 text-sm text-stone-600">
                        No payment records yet.
                    </p>
                )}
            </div>
        </AdminLayout>
    );
}

function EarlyPaymentAllocation({
    paymentId,
    versions,
}: {
    paymentId: number;
    versions: Array<{
        id: number;
        version_number: number;
        quotation: { reference: string };
    }>;
}) {
    const form = useForm({ quotation_version_id: "", reason: "" });
    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                form.post(`/admin/payments/${paymentId}/reallocate`);
            }}
            className="flex flex-wrap items-center gap-2"
        >
            <select
                required
                value={form.data.quotation_version_id}
                onChange={(event) =>
                    form.setData("quotation_version_id", event.target.value)
                }
                aria-label="Accepted quotation version for early payment"
                className="max-w-56 rounded border px-2 py-1 text-xs"
            >
                <option value="">Allocate to accepted quotation</option>
                {versions.map((version) => (
                    <option key={version.id} value={version.id}>
                        {version.quotation.reference} v{version.version_number}
                    </option>
                ))}
            </select>
            <input
                required
                value={form.data.reason}
                onChange={(event) => form.setData("reason", event.target.value)}
                aria-label="Reason for early payment allocation"
                placeholder="Reason"
                className="max-w-52 rounded border px-2 py-1 text-xs"
            />
            <button className="rounded border px-2 py-1 text-xs">
                Allocate
            </button>
            {form.errors.quotation_version_id && (
                <span role="alert" className="w-full text-xs text-rose-700">
                    {form.errors.quotation_version_id}
                </span>
            )}
            {form.errors.reason && (
                <span role="alert" className="w-full text-xs text-rose-700">
                    {form.errors.reason}
                </span>
            )}
        </form>
    );
}

function FormError({ error }: { error?: string }) {
    return error ? (
        <span role="alert" className="mt-1 block text-sm text-rose-700">
            {error}
        </span>
    ) : null;
}
