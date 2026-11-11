import { Link } from "@inertiajs/react";
import {
    AlertCircle,
    ArrowRight,
    CalendarCheck2,
    ClipboardList,
    FileText,
    ReceiptText,
} from "lucide-react";
import { formatManilaEvent } from "../../../lib/date-time";

type DashboardCard = {
    label: string;
    count: number;
    href: string;
    description: string;
    tone: "neutral" | "attention" | "success";
};

type UpcomingReservation = {
    id: number;
    reference: string;
    customerName: string;
    eventDate: string;
    eventStartTime: string | null;
    guestCount: number | null;
    status: string;
};

type QuickAction = {
    label: string;
    description: string;
    href: string;
};

const cardIcon = (label: string) => {
    if (label.includes("Payment")) return ReceiptText;
    if (label.includes("Quotation")) return FileText;
    if (label.includes("reservation")) return CalendarCheck2;

    return ClipboardList;
};

export function OperationsDashboard({
    cards,
    upcomingReservations,
    quickActions,
}: {
    cards: DashboardCard[];
    upcomingReservations: UpcomingReservation[];
    quickActions: QuickAction[];
}) {
    return (
        <div className="space-y-6">
            <section aria-labelledby="attention-heading">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-700">
                            Operations
                        </p>
                        <h2
                            id="attention-heading"
                            className="mt-1 text-xl font-semibold text-slate-950"
                        >
                            Current work queues
                        </h2>
                    </div>
                    <p className="max-w-xl text-sm text-slate-600">
                        Counts reflect records you are authorized to view. They
                        do not reserve dates or change customer records.
                    </p>
                </div>
                {cards.length ? (
                    <div className="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        {cards.map((card) => {
                            const Icon = cardIcon(card.label);
                            const attention = card.tone === "attention";

                            return (
                                <Link
                                    key={card.label}
                                    href={card.href}
                                    className={`group rounded-2xl border p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md ${attention ? "border-amber-200 bg-amber-50" : "border-slate-200 bg-white"}`}
                                >
                                    <div className="flex items-start justify-between gap-4">
                                        <Icon
                                            size={20}
                                            className={
                                                attention
                                                    ? "text-amber-700"
                                                    : "text-emerald-700"
                                            }
                                            aria-hidden="true"
                                        />
                                        <span className="text-3xl font-semibold tabular-nums text-slate-950">
                                            {card.count}
                                        </span>
                                    </div>
                                    <h3 className="mt-5 font-semibold text-slate-950">
                                        {card.label}
                                    </h3>
                                    <p className="mt-1 text-sm leading-6 text-slate-600">
                                        {card.description}
                                    </p>
                                    <span className="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-emerald-800 group-hover:underline">
                                        Open workspace <ArrowRight size={15} />
                                    </span>
                                </Link>
                            );
                        })}
                    </div>
                ) : (
                    <div className="mt-4 rounded-2xl border border-dashed border-slate-300 bg-white p-6 text-sm text-slate-600">
                        Your current permissions do not include an operational
                        queue. Ask an Administrator if you need access to a
                        specific workspace.
                    </div>
                )}
            </section>

            <section className="grid gap-5 xl:grid-cols-[1.35fr_.65fr]">
                <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 className="font-semibold text-slate-950">
                                Upcoming confirmed events
                            </h2>
                            <p className="mt-1 text-sm text-slate-600">
                                Confirmed and completed events remain visible;
                                completion does not release historical capacity.
                            </p>
                        </div>
                        {upcomingReservations.length > 0 && (
                            <Link
                                href="/admin/reservations"
                                className="text-sm font-semibold text-emerald-800 hover:underline"
                            >
                                View reservations
                            </Link>
                        )}
                    </div>
                    {upcomingReservations.length ? (
                        <ul className="mt-4 divide-y divide-slate-100">
                            {upcomingReservations.map((reservation) => (
                                <li
                                    key={reservation.id}
                                    className="flex flex-wrap items-start justify-between gap-3 py-4 first:pt-0"
                                >
                                    <div>
                                        <p className="font-medium text-slate-950">
                                            {reservation.customerName}
                                        </p>
                                        <p className="mt-1 text-sm text-slate-600">
                                            {formatManilaEvent(
                                                reservation.eventDate,
                                                reservation.eventStartTime,
                                            )}
                                            {reservation.guestCount
                                                ? ` · ${reservation.guestCount} guests`
                                                : ""}
                                        </p>
                                    </div>
                                    <div className="text-right">
                                        <p className="font-mono text-xs text-slate-500">
                                            {reservation.reference}
                                        </p>
                                        <p className="mt-1 text-xs font-semibold capitalize text-emerald-800">
                                            {reservation.status}
                                        </p>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <p className="mt-4 rounded-xl bg-slate-50 p-4 text-sm text-slate-600">
                            No upcoming confirmed events are available in your
                            permitted records.
                        </p>
                    )}
                </div>

                <div className="rounded-2xl border border-slate-200 bg-slate-950 p-5 text-white shadow-sm">
                    <div className="flex items-center gap-2">
                        <AlertCircle
                            size={19}
                            className="text-amber-300"
                            aria-hidden="true"
                        />
                        <h2 className="font-semibold">Start a task</h2>
                    </div>
                    <p className="mt-2 text-sm leading-6 text-slate-300">
                        Only actions your current server-side permissions allow
                        are shown here.
                    </p>
                    {quickActions.length ? (
                        <ul className="mt-5 space-y-3">
                            {quickActions.map((action) => (
                                <li key={action.href}>
                                    <Link
                                        href={action.href}
                                        className="block rounded-xl border border-slate-700 p-3 transition hover:border-emerald-400 hover:bg-slate-900"
                                    >
                                        <span className="block text-sm font-semibold">
                                            {action.label}
                                        </span>
                                        <span className="mt-1 block text-xs leading-5 text-slate-300">
                                            {action.description}
                                        </span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <p className="mt-5 text-sm text-slate-300">
                            No create actions are assigned to this account.
                        </p>
                    )}
                </div>
            </section>
        </div>
    );
}
