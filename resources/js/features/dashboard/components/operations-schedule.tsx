import { useState } from "react";
import { CalendarDays, MapPin } from "lucide-react";
import { demoEvents } from "../demo-data";

export function OperationsSchedule() {
    const [status, setStatus] = useState("all");
    const events = demoEvents.filter(
        (event) => status === "all" || event.status === status,
    );
    const dates = [...new Set(demoEvents.map((event) => event.date))];
    return (
        <section className="grid items-start gap-5 xl:grid-cols-[1.5fr_1fr]">
            <div className="rounded-2xl border bg-white p-5 dark:bg-slate-900 dark:text-slate-100">
                <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 className="font-semibold">Upcoming events</h2>
                        <p className="text-sm text-slate-500">
                            Illustrative schedule · next 7 days
                        </p>
                    </div>
                    <label className="text-sm">
                        Demo status
                        <select
                            value={status}
                            onChange={(event) => setStatus(event.target.value)}
                        >
                            <option value="all">All statuses</option>
                            <option>Confirmed</option>
                            <option>Tentative</option>
                        </select>
                    </label>
                </div>
                <ul className="divide-y">
                    {events.map((event) => (
                        <li key={event.id} className="flex gap-4 py-4">
                            <CalendarDays
                                aria-hidden
                                className="mt-1 shrink-0 text-emerald-700"
                                size={22}
                            />
                            <div className="min-w-0 flex-1">
                                <div className="flex flex-wrap justify-between gap-2">
                                    <h3 className="font-semibold">
                                        {event.type} · {event.customer}
                                    </h3>
                                    <span className="rounded-full border px-2 py-0.5 text-xs">
                                        {event.status}
                                    </span>
                                </div>
                                <p className="mt-1 text-sm">
                                    {event.date} · {event.start}–{event.end} ·{" "}
                                    {event.guests} guests
                                </p>
                                <p className="mt-1 flex items-center gap-1 text-sm text-slate-500">
                                    <MapPin aria-hidden size={14} />
                                    {event.venue}
                                </p>
                            </div>
                        </li>
                    ))}
                </ul>
                <p className="mt-3 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-950 dark:bg-emerald-950 dark:text-emerald-100">
                    The two October 17 events overlap. This is not automatically
                    a conflict; simultaneous events require manual capacity
                    review.
                </p>
            </div>
            <div className="rounded-2xl border bg-white p-5 dark:bg-slate-900 dark:text-slate-100">
                <h2 className="font-semibold">Capacity preview</h2>
                <p className="mt-1 text-sm text-slate-500">
                    Daily load, not a capacity limit or availability promise.
                </p>
                <ul className="mt-5 space-y-4">
                    {dates.map((date) => {
                        const day = demoEvents.filter(
                            (event) => event.date === date,
                        );
                        return (
                            <li key={date} className="rounded-xl border p-3">
                                <div className="flex justify-between">
                                    <span>{date}</span>
                                    <strong>
                                        {day.length} event
                                        {day.length === 1 ? "" : "s"}
                                    </strong>
                                </div>
                                <p className="mt-1 text-sm text-slate-500">
                                    {day.reduce(
                                        (sum, event) => sum + event.guests,
                                        0,
                                    )}{" "}
                                    scheduled guests
                                </p>
                                {day.some((event) => event.review) && (
                                    <p className="mt-2 text-sm font-medium text-amber-700 dark:text-amber-300">
                                        Demo notice: review staffing and
                                        logistics
                                    </p>
                                )}
                            </li>
                        );
                    })}
                </ul>
                <p className="mt-4 text-xs text-slate-500">
                    No automatic capacity optimization or conflict detection is
                    implemented.
                </p>
            </div>
        </section>
    );
}
