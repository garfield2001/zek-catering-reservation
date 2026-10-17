import {
    AlertCircle,
    CalendarCheck,
    CalendarDays,
    CircleDollarSign,
    Clock,
    FileText,
    Inbox,
    Wallet,
} from "lucide-react";
import {
    demoActivity,
    demoDeadlines,
    demoEvents,
    demoSummary,
    formatDemoMoney,
} from "../demo-data";

export function OperationsOverview({
    totals,
}: {
    totals: ReturnType<typeof demoSummary>;
}) {
    const attention = [
        ["Review new inquiries", totals.inquiries, "Inquiry review"],
        [
            "Prepare or follow up quotations",
            totals.quotations,
            "Quotation workspace",
        ],
        [
            "Verify downpayment proofs",
            totals.proofs,
            "Manual payment verification",
        ],
        ["Final payments due soon", demoDeadlines.length, "Payment review"],
        ["Expiring quotations", 2, "Quotation follow-up"],
        ["Change / reschedule requests", 1, "Reservation review"],
        ["Capacity review notices", 1, "Operational review"],
    ];
    const statistics = [
        { label: "New inquiries", value: totals.inquiries, icon: Inbox },
        {
            label: "Quotations awaiting action",
            value: totals.quotations,
            icon: FileText,
        },
        {
            label: "Payments awaiting verification",
            value: totals.proofs,
            icon: Clock,
        },
        {
            label: "Confirmed reservations",
            value: totals.confirmed,
            icon: CalendarCheck,
        },
        {
            label: "Upcoming confirmed events",
            value: demoEvents.filter((event) => event.status === "Confirmed")
                .length,
            icon: CalendarDays,
            note: "Next 7 days",
        },
        {
            label: "Confirmed booking value",
            value: formatDemoMoney(totals.booking),
            icon: CircleDollarSign,
        },
        {
            label: "Verified payments collected",
            value: formatDemoMoney(totals.collected),
            icon: Wallet,
        },
        {
            label: "Outstanding balances",
            value: formatDemoMoney(totals.outstanding),
            icon: Clock,
        },
    ];
    return (
        <>
            <section
                aria-label="Needs attention"
                className="rounded-2xl border border-amber-200 bg-amber-50 p-5 dark:border-amber-800 dark:bg-slate-900 dark:text-slate-100"
            >
                <div className="flex items-center gap-2">
                    <AlertCircle
                        size={22}
                        className="text-amber-700"
                        aria-hidden
                    />
                    <h2 className="text-lg font-semibold">Needs attention</h2>
                    <span className="ml-auto text-xs text-amber-800 dark:text-amber-200">
                        Preview only
                    </span>
                </div>
                <p className="mt-2 text-sm text-slate-600 dark:text-slate-300">
                    Illustrative queues for the selected reporting period. These
                    are not actionable customer records.
                </p>
                <ul className="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    {attention.map(([label, count, workspace]) => (
                        <li
                            key={label}
                            className="rounded-xl bg-white p-4 dark:bg-slate-800"
                        >
                            <strong className="text-2xl">{count}</strong>
                            <p className="mt-1 text-sm font-medium">{label}</p>
                            <p className="mt-2 text-xs text-slate-500 dark:text-slate-400">
                                {workspace} · Coming soon
                            </p>
                        </li>
                    ))}
                </ul>
            </section>
            <section
                aria-label="Demo performance indicators"
                className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4"
            >
                {statistics.map(({ label, value, icon: Icon, note }) => (
                    <div
                        key={label}
                        className="rounded-2xl border bg-white p-5 dark:bg-slate-900 dark:text-slate-100"
                    >
                        <div className="flex items-center justify-between gap-2">
                            <p className="text-sm text-slate-500 dark:text-slate-400">
                                {label}
                            </p>
                            <Icon
                                aria-hidden
                                size={19}
                                className="shrink-0 text-emerald-700"
                            />
                        </div>
                        <p className="mt-3 text-2xl font-semibold tracking-tight">
                            {value}
                        </p>
                        <p className="mt-1 text-xs text-slate-500">
                            {note ?? "Selected reporting period"} · Demo
                        </p>
                    </div>
                ))}
            </section>
        </>
    );
}

export function FinancialActivity({
    totals,
}: {
    totals: ReturnType<typeof demoSummary>;
}) {
    return (
        <section className="grid gap-5 xl:grid-cols-2">
            <div className="rounded-2xl border bg-white p-5 dark:bg-slate-900 dark:text-slate-100">
                <h2 className="font-semibold">Financial overview</h2>
                <p className="mt-1 text-sm text-slate-500">
                    Booking value is not cash collected, earned revenue or
                    profit.
                </p>
                <dl className="mt-5 space-y-3">
                    {[
                        ["Confirmed booking value", totals.booking],
                        ["Verified collected payments", totals.collected],
                        ["Outstanding balances", totals.outstanding],
                        [
                            "Downpayments awaiting verification",
                            totals.unverified,
                        ],
                    ].map(([label, amount]) => (
                        <div
                            key={label}
                            className="flex flex-wrap justify-between gap-2"
                        >
                            <dt className="text-sm">{label}</dt>
                            <dd className="font-medium">
                                {formatDemoMoney(Number(amount))}
                            </dd>
                        </div>
                    ))}
                </dl>
                <div
                    className="mt-5 h-3 overflow-hidden rounded-full bg-slate-200"
                    role="img"
                    aria-label={`${Math.round((totals.collected / totals.booking) * 100)} percent of demo booking value collected`}
                >
                    <div
                        className="h-full rounded-full bg-emerald-700"
                        style={{
                            width: `${(totals.collected / totals.booking) * 100}%`,
                        }}
                    />
                </div>
                <p className="mt-2 text-xs text-slate-500">
                    Unverified proofs are not included in collected payments;
                    they remain part of outstanding balances.
                </p>
                <h3 className="mt-5 text-sm font-semibold">
                    Illustrative final-payment deadlines
                </h3>
                <ul className="mt-2 space-y-2 text-sm">
                    {demoDeadlines.map((deadline) => (
                        <li
                            key={deadline.customer}
                            className="flex flex-wrap justify-between gap-2"
                        >
                            <span>
                                {deadline.customer} · {deadline.due}
                            </span>
                            <span>{formatDemoMoney(deadline.amount)}</span>
                        </li>
                    ))}
                </ul>
            </div>
            <div className="rounded-2xl border bg-white p-5 dark:bg-slate-900 dark:text-slate-100">
                <h2 className="font-semibold">Recent activity · Demo</h2>
                <p className="mt-1 text-sm text-slate-500">
                    Illustrative operational timeline, not the real Activity
                    History.
                </p>
                <ol className="mt-5 space-y-5">
                    {demoActivity.map(([time, title, detail]) => (
                        <li key={time} className="flex gap-4">
                            <span className="text-xs tabular-nums text-slate-500">
                                {time}
                            </span>
                            <div className="border-l-2 border-emerald-200 pl-4">
                                <p className="text-sm font-medium">{title}</p>
                                <p className="text-xs text-slate-500">
                                    {detail}
                                </p>
                            </div>
                        </li>
                    ))}
                </ol>
            </div>
        </section>
    );
}
