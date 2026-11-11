import { Link } from "@inertiajs/react";
import { formatPesos } from "../../packages/money";
import { formatManilaTimestamp } from "../../../lib/date-time";
import type {
    AcceptedQuotationVersion,
    PaymentListItem,
} from "../payment-types";
import { EarlyPaymentAllocationDialog } from "./early-payment-allocation-dialog";
import { PaymentReasonDialog } from "./payment-reason-dialog";
import { VerifyPayment } from "./verify-payment";

export function PaymentHistoryTable({
    payments,
    acceptedVersions,
    canVerify,
    canViewQuotations,
}: {
    payments: PaymentListItem[];
    acceptedVersions: AcceptedQuotationVersion[];
    canVerify: boolean;
    canViewQuotations: boolean;
}) {
    return (
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
                    {payments.map((payment) => (
                        <tr key={payment.id} className="border-b">
                            <td className="p-3">
                                #{payment.id}
                                <br />
                                <span className="text-stone-500">
                                    {payment.paid_at
                                        ? formatManilaTimestamp(payment.paid_at)
                                        : "Submitted receipt"}
                                </span>
                            </td>
                            <td>
                                {payment.quotation && canViewQuotations ? (
                                    <Link
                                        href={`/admin/quotations/${payment.quotation_id}`}
                                        className="font-semibold text-emerald-800 underline"
                                    >
                                        {payment.quotation.reference}
                                    </Link>
                                ) : (
                                    (payment.reservation_request?.reference ??
                                    "Exceptional / unallocated")
                                )}
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
                                <PaymentStatus status={payment.status} />
                            </td>
                            <td className="p-3">
                                <PaymentEvidence payment={payment} />
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
                                            <PaymentReasonDialog
                                                paymentId={payment.id}
                                                action="reject"
                                            />
                                        </div>
                                    )}
                                {canVerify && payment.status === "verified" && (
                                    <div className="flex flex-wrap gap-2">
                                        <PaymentReasonDialog
                                            paymentId={payment.id}
                                            action="reverse"
                                        />
                                        {payment.quotation === null && (
                                            <EarlyPaymentAllocationDialog
                                                paymentId={payment.id}
                                                versions={acceptedVersions}
                                            />
                                        )}
                                    </div>
                                )}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
            {payments.length === 0 && (
                <p className="p-6 text-sm text-stone-600">
                    No payment submissions or offline payment records have been
                    recorded.
                </p>
            )}
        </div>
    );
}

function PaymentEvidence({ payment }: { payment: PaymentListItem }) {
    if (payment.evidence_versions.length > 0) {
        return (
            <div className="mb-2 flex flex-wrap gap-2">
                {payment.evidence_versions.map((evidence) => (
                    <span key={evidence.id} className="flex gap-1">
                        <a
                            href={`/admin/payments/${payment.id}/evidence/${evidence.id}/preview`}
                            target="_blank"
                            rel="noreferrer"
                            className="rounded border px-2 py-1 text-xs"
                        >
                            Preview v{evidence.version_number}
                        </a>
                        <a
                            href={`/admin/payments/${payment.id}/evidence/${evidence.id}`}
                            className="rounded border px-2 py-1 text-xs"
                        >
                            Download
                        </a>
                    </span>
                ))}
            </div>
        );
    }

    if (!payment.has_proof) return null;

    return (
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
    );
}

function PaymentStatus({ status }: { status: string }) {
    const tone =
        {
            verified: "bg-emerald-100 text-emerald-950",
            pending_verification: "bg-amber-100 text-amber-950",
            rejected: "bg-rose-100 text-rose-950",
            reversed: "bg-slate-200 text-slate-800",
        }[status] ?? "bg-stone-100 text-stone-800";

    return (
        <span
            className={`rounded-full px-2 py-1 text-xs font-semibold ${tone}`}
        >
            {status.replaceAll("_", " ")}
        </span>
    );
}
