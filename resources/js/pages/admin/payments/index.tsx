import { Head, Link } from "@inertiajs/react";
import { PaymentHistoryTable } from "../../../features/payments/components/payment-history-table";
import { PaymentRecordForm } from "../../../features/payments/components/payment-record-form";
import type {
    AcceptedQuotationVersion,
    PaymentListItem,
    PaymentMethod,
} from "../../../features/payments/payment-types";
import AdminLayout from "../../../layouts/admin-layout";

export default function PaymentsIndex({
    payments,
    methods,
    quotations,
    acceptedVersions,
    canRecord,
    canVerify,
    canRecordEarlyException,
    canViewQuotations,
}: {
    payments: { data: PaymentListItem[]; links: unknown };
    methods: PaymentMethod[];
    quotations: Array<{ id: number; reference: string }>;
    acceptedVersions: AcceptedQuotationVersion[];
    canRecord: boolean;
    canVerify: boolean;
    canRecordEarlyException: boolean;
    canViewQuotations: boolean;
}) {
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
                <PaymentRecordForm
                    methods={methods}
                    quotations={quotations}
                    canRecordEarlyException={canRecordEarlyException}
                />
            )}
            <PaymentHistoryTable
                payments={payments.data}
                acceptedVersions={acceptedVersions}
                canVerify={canVerify}
                canViewQuotations={canViewQuotations}
            />
        </AdminLayout>
    );
}
