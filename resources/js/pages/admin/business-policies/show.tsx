import { useForm } from "@inertiajs/react";
import AdminLayout from "../../../layouts/admin-layout";

type Policy = {
    minimum_advance_booking_days: number;
    minimum_rescheduling_notice_days: number;
    cancellation_notice_days: number;
    event_duration_hours: number;
    daily_confirmed_event_capacity: number;
    daily_guest_capacity: number | null;
    emergency_booking_requires_administrator: boolean;
    quotation_validity_days: number;
    allow_quotation_validity_overrides: boolean;
    reservation_payment_type: "fixed_amount" | "percentage";
    reservation_payment_amount: string | null;
    reservation_payment_basis_points: number | null;
    reservation_payment_deadline_hours: number;
    final_payment_days_before_event: number;
    customer_payment_upload_cutoff_days: number;
    payment_account_name: string;
    payment_ewallet_number: string;
    payment_bank_account: string;
    payment_additional_instructions: string;
    menu_finalization_days_after_acceptance: number;
    cancellation_refund_guidance: string;
    updated_at: string;
};

export default function BusinessPoliciesShow({
    policy,
    canManage,
}: {
    policy: Policy;
    canManage: boolean;
}) {
    const form = useForm({
        ...policy,
        reservation_payment_amount: policy.reservation_payment_amount ?? "",
        reservation_payment_basis_points:
            policy.reservation_payment_basis_points ?? 0,
        daily_guest_capacity: policy.daily_guest_capacity?.toString() ?? "",
    });
    const error = (field: keyof typeof form.data) =>
        form.errors[field] ? (
            <p role="alert" className="mt-1 text-sm text-rose-700">
                {form.errors[field]}
            </p>
        ) : null;

    return (
        <AdminLayout
            title="Business Policies"
            description="Current rules for new inquiries and quotations. Issued quotations retain their own saved policy snapshot."
            section="quotations"
        >
            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    form.put("/admin/business-policies");
                }}
                className="max-w-3xl space-y-6"
            >
                <section className="rounded-2xl border bg-white p-5">
                    <h2 className="font-semibold">Booking and event</h2>
                    <div className="mt-4 grid gap-4 sm:grid-cols-2">
                        <label className="text-sm font-medium">
                            Minimum advance booking (days)
                            <input
                                type="number"
                                min="1"
                                disabled={!canManage}
                                value={form.data.minimum_advance_booking_days}
                                onChange={(event) =>
                                    form.setData(
                                        "minimum_advance_booking_days",
                                        Number(event.target.value),
                                    )
                                }
                                className="mt-1 w-full"
                            />
                            {error("minimum_advance_booking_days")}
                        </label>
                        <div className="text-sm font-medium">
                            Event duration
                            <p className="mt-1 rounded-lg bg-slate-50 p-2 text-slate-700">
                                {policy.event_duration_hours} hours (fixed)
                            </p>
                        </div>
                        <label className="text-sm font-medium">
                            Minimum rescheduling notice (days)
                            <input
                                type="number"
                                min="1"
                                disabled={!canManage}
                                value={
                                    form.data.minimum_rescheduling_notice_days
                                }
                                onChange={(event) =>
                                    form.setData(
                                        "minimum_rescheduling_notice_days",
                                        Number(event.target.value),
                                    )
                                }
                                className="mt-1 w-full"
                            />
                            {error("minimum_rescheduling_notice_days")}
                        </label>
                        <label className="text-sm font-medium">
                            Cancellation review guideline (days)
                            <input
                                type="number"
                                min="1"
                                disabled={!canManage}
                                value={form.data.cancellation_notice_days}
                                onChange={(event) =>
                                    form.setData(
                                        "cancellation_notice_days",
                                        Number(event.target.value),
                                    )
                                }
                                className="mt-1 w-full"
                            />
                            <span className="mt-1 block text-xs font-normal text-slate-500">
                                Late requests still require Administrator
                                review; this does not impose an automatic
                                penalty.
                            </span>
                            {error("cancellation_notice_days")}
                        </label>
                    </div>
                    <label className="mt-4 flex gap-3 text-sm">
                        <input
                            type="checkbox"
                            disabled={!canManage}
                            checked={
                                form.data
                                    .emergency_booking_requires_administrator
                            }
                            onChange={(event) =>
                                form.setData(
                                    "emergency_booking_requires_administrator",
                                    event.target.checked,
                                )
                            }
                        />
                        Emergency bookings require Administrator review.
                    </label>
                    <p className="mt-3 text-sm text-slate-600">
                        Customer event times and venues remain tentative. This
                        system does not automatically hold availability.
                    </p>
                </section>

                <section className="rounded-2xl border bg-white p-5">
                    <h2 className="font-semibold">Reservation availability</h2>
                    <p className="mt-2 text-sm text-slate-600">
                        Only Administrator-confirmed reservations consume this
                        capacity. Inquiries, quotations, accepted quotations,
                        and verified payments do not hold a date.
                    </p>
                    <div className="mt-4 grid gap-4 sm:grid-cols-2">
                        <PolicyNumber
                            label="Confirmed events allowed each day"
                            value={form.data.daily_confirmed_event_capacity}
                            disabled={!canManage}
                            onChange={(value) =>
                                form.setData(
                                    "daily_confirmed_event_capacity",
                                    value,
                                )
                            }
                            error={form.errors.daily_confirmed_event_capacity}
                        />
                        <PolicyNumber
                            label="Latest customer payment upload (days before event)"
                            value={
                                form.data.customer_payment_upload_cutoff_days
                            }
                            disabled={!canManage}
                            onChange={(value) =>
                                form.setData(
                                    "customer_payment_upload_cutoff_days",
                                    value,
                                )
                            }
                            error={
                                form.errors.customer_payment_upload_cutoff_days
                            }
                        />
                        <label className="text-sm font-medium">
                            Daily guest capacity (optional)
                            <input
                                type="number"
                                min="1"
                                disabled={!canManage}
                                value={form.data.daily_guest_capacity}
                                onChange={(event) =>
                                    form.setData(
                                        "daily_guest_capacity",
                                        event.target.value,
                                    )
                                }
                                className="mt-1 w-full"
                            />
                            <span className="mt-1 block text-xs font-normal text-slate-500">
                                Leave blank until your operational guest limit
                                is known.
                            </span>
                            {error("daily_guest_capacity")}
                        </label>
                    </div>
                    {canManage && (
                        <a
                            href="/admin/reservations/capacity"
                            className="mt-4 inline-block text-sm font-semibold text-emerald-800 underline"
                        >
                            Manage date-specific capacity overrides
                        </a>
                    )}
                </section>

                <section className="rounded-2xl border bg-white p-5">
                    <h2 className="font-semibold">Cancellation and refunds</h2>
                    <p className="mt-2 text-sm text-slate-600">
                        This guidance is shown to customers requesting a
                        cancellation. It does not promise a refund or set an
                        automatic financial penalty.
                    </p>
                    <label className="mt-4 block text-sm font-medium">
                        Customer guidance
                        <textarea
                            disabled={!canManage}
                            value={form.data.cancellation_refund_guidance}
                            onChange={(event) =>
                                form.setData(
                                    "cancellation_refund_guidance",
                                    event.target.value,
                                )
                            }
                            className="mt-1 w-full"
                        />
                        {error("cancellation_refund_guidance")}
                    </label>
                </section>
                <section className="rounded-2xl border bg-white p-5">
                    <h2 className="font-semibold">
                        Customer payment instructions
                    </h2>
                    <p className="mt-2 text-sm text-slate-600">
                        These details appear to customers before they upload a
                        receipt. Replace the sample placeholders before
                        collecting real payments.
                    </p>
                    <div className="mt-4 grid gap-4 sm:grid-cols-2">
                        <PolicyText
                            label="Account name"
                            value={form.data.payment_account_name}
                            disabled={!canManage}
                            onChange={(value) =>
                                form.setData("payment_account_name", value)
                            }
                            error={form.errors.payment_account_name}
                        />
                        <PolicyText
                            label="E-wallet number"
                            value={form.data.payment_ewallet_number}
                            disabled={!canManage}
                            onChange={(value) =>
                                form.setData("payment_ewallet_number", value)
                            }
                            error={form.errors.payment_ewallet_number}
                        />
                        <PolicyText
                            label="Bank account"
                            value={form.data.payment_bank_account}
                            disabled={!canManage}
                            onChange={(value) =>
                                form.setData("payment_bank_account", value)
                            }
                            error={form.errors.payment_bank_account}
                        />
                        <label className="text-sm font-medium sm:col-span-2">
                            Additional instructions
                            <textarea
                                disabled={!canManage}
                                value={
                                    form.data.payment_additional_instructions
                                }
                                onChange={(event) =>
                                    form.setData(
                                        "payment_additional_instructions",
                                        event.target.value,
                                    )
                                }
                                className="mt-1 w-full"
                            />
                            {error("payment_additional_instructions")}
                        </label>
                    </div>
                </section>

                <section className="rounded-2xl border bg-white p-5">
                    <h2 className="font-semibold">Quotation</h2>
                    <div className="mt-4 grid gap-4 sm:grid-cols-2">
                        <label className="text-sm font-medium">
                            Standard validity (days)
                            <input
                                type="number"
                                min="1"
                                disabled={!canManage}
                                value={form.data.quotation_validity_days}
                                onChange={(event) =>
                                    form.setData(
                                        "quotation_validity_days",
                                        Number(event.target.value),
                                    )
                                }
                                className="mt-1 w-full"
                            />
                            {error("quotation_validity_days")}
                        </label>
                        <label className="mt-7 flex gap-3 text-sm">
                            <input
                                type="checkbox"
                                disabled={!canManage}
                                checked={
                                    form.data.allow_quotation_validity_overrides
                                }
                                onChange={(event) =>
                                    form.setData(
                                        "allow_quotation_validity_overrides",
                                        event.target.checked,
                                    )
                                }
                            />
                            Authorized issuers may override validity.
                        </label>
                    </div>
                </section>

                <section className="rounded-2xl border bg-white p-5">
                    <h2 className="font-semibold">
                        Payment and menu deadlines
                    </h2>
                    <div className="mt-4 grid gap-4 sm:grid-cols-2">
                        <label className="text-sm font-medium">
                            Reservation payment
                            <select
                                disabled={!canManage}
                                value={form.data.reservation_payment_type}
                                onChange={(event) =>
                                    form.setData(
                                        "reservation_payment_type",
                                        event.target.value as
                                            "fixed_amount" | "percentage",
                                    )
                                }
                                className="mt-1 w-full"
                            >
                                <option value="fixed_amount">
                                    Fixed peso amount
                                </option>
                                <option value="percentage">Percentage</option>
                            </select>
                            {error("reservation_payment_type")}
                        </label>
                        {form.data.reservation_payment_type ===
                        "fixed_amount" ? (
                            <label className="text-sm font-medium">
                                Fixed amount (PHP)
                                <input
                                    inputMode="decimal"
                                    disabled={!canManage}
                                    value={form.data.reservation_payment_amount}
                                    onChange={(event) =>
                                        form.setData(
                                            "reservation_payment_amount",
                                            event.target.value,
                                        )
                                    }
                                    className="mt-1 w-full"
                                />
                                {error("reservation_payment_amount")}
                            </label>
                        ) : (
                            <label className="text-sm font-medium">
                                Percentage
                                <input
                                    type="number"
                                    min="0"
                                    max="100"
                                    step="0.01"
                                    disabled={!canManage}
                                    value={
                                        form.data
                                            .reservation_payment_basis_points /
                                        100
                                    }
                                    onChange={(event) =>
                                        form.setData(
                                            "reservation_payment_basis_points",
                                            Math.round(
                                                Number(event.target.value) *
                                                    100,
                                            ),
                                        )
                                    }
                                    className="mt-1 w-full"
                                />
                                {error("reservation_payment_basis_points")}
                            </label>
                        )}
                        <PolicyNumber
                            label="Reservation payment deadline after acceptance (hours)"
                            value={form.data.reservation_payment_deadline_hours}
                            disabled={!canManage}
                            onChange={(value) =>
                                form.setData(
                                    "reservation_payment_deadline_hours",
                                    value,
                                )
                            }
                            error={
                                form.errors.reservation_payment_deadline_hours
                            }
                        />
                        <PolicyNumber
                            label="Final payment deadline before event (days)"
                            value={form.data.final_payment_days_before_event}
                            disabled={!canManage}
                            onChange={(value) =>
                                form.setData(
                                    "final_payment_days_before_event",
                                    value,
                                )
                            }
                            error={form.errors.final_payment_days_before_event}
                        />
                        <PolicyNumber
                            label="Menu finalization after acceptance (days)"
                            value={
                                form.data
                                    .menu_finalization_days_after_acceptance
                            }
                            disabled={!canManage}
                            onChange={(value) =>
                                form.setData(
                                    "menu_finalization_days_after_acceptance",
                                    value,
                                )
                            }
                            error={
                                form.errors
                                    .menu_finalization_days_after_acceptance
                            }
                        />
                    </div>
                </section>
                {canManage ? (
                    <button
                        disabled={form.processing}
                        className="rounded-lg bg-emerald-800 px-5 py-3 text-sm font-semibold text-white"
                    >
                        Save Business Policies
                    </button>
                ) : (
                    <p className="text-sm text-slate-600">
                        Your account can view these policies but cannot change
                        them. Ask an Administrator for updates.
                    </p>
                )}
            </form>
        </AdminLayout>
    );
}

function PolicyText({
    label,
    value,
    disabled,
    onChange,
    error,
}: {
    label: string;
    value: string;
    disabled: boolean;
    onChange: (value: string) => void;
    error?: string;
}) {
    return (
        <label className="text-sm font-medium">
            {label}
            <input
                disabled={disabled}
                value={value}
                onChange={(event) => onChange(event.target.value)}
                className="mt-1 w-full"
            />
            {error && (
                <p role="alert" className="mt-1 text-sm text-rose-700">
                    {error}
                </p>
            )}
        </label>
    );
}

function PolicyNumber({
    label,
    value,
    disabled,
    onChange,
    error,
}: {
    label: string;
    value: number;
    disabled: boolean;
    onChange: (value: number) => void;
    error?: string;
}) {
    return (
        <label className="text-sm font-medium">
            {label}
            <input
                type="number"
                min="0"
                disabled={disabled}
                value={value}
                onChange={(event) => onChange(Number(event.target.value))}
                className="mt-1 w-full"
            />
            {error && <p className="mt-1 text-sm text-rose-700">{error}</p>}
        </label>
    );
}
