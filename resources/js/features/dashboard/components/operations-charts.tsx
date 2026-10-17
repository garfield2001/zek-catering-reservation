import { useState } from "react";
import {
    Bar,
    BarChart,
    CartesianGrid,
    Legend,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from "recharts";
import { demoMonths, demoSummary, formatDemoMoney } from "../demo-data";

export function OperationsCharts({ months }: { months: typeof demoMonths }) {
    const [view, setView] = useState<"reservations" | "payments">(
        "reservations",
    );
    const totals = demoSummary(months);
    const statuses = [
        { name: "Confirmed", count: totals.confirmed },
        { name: "Pending", count: totals.pending },
        { name: "Cancelled", count: totals.cancelled },
    ];
    return (
        <section
            className="grid gap-5 xl:grid-cols-[1.5fr_1fr]"
            aria-label="Demo analytics"
        >
            <div className="min-w-0 rounded-2xl border bg-white p-5 dark:bg-slate-900 dark:text-slate-100">
                <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
                    <h2 className="font-semibold">Monthly overview</h2>
                    <div className="flex gap-2" aria-label="Chart view">
                        {(["reservations", "payments"] as const).map((tab) => (
                            <button
                                key={tab}
                                onClick={() => setView(tab)}
                                aria-pressed={view === tab}
                                className={`rounded-lg px-3 py-2 text-sm capitalize ${view === tab ? "bg-emerald-800 text-white" : "border"}`}
                            >
                                {tab}
                            </button>
                        ))}
                    </div>
                </div>
                <ResponsiveContainer width="100%" height={260}>
                    <BarChart data={months} accessibilityLayer>
                        <CartesianGrid
                            vertical={false}
                            stroke="#cbd5e1"
                            strokeDasharray="3 3"
                        />
                        <XAxis dataKey="month" stroke="#64748b" />
                        <YAxis
                            width={65}
                            stroke="#64748b"
                            tickFormatter={(value) =>
                                view === "payments"
                                    ? `${Number(value) / 1000}k`
                                    : String(value)
                            }
                        />
                        <Tooltip
                            formatter={(value) =>
                                view === "payments"
                                    ? formatDemoMoney(Number(value))
                                    : String(value)
                            }
                            contentStyle={{
                                borderRadius: 12,
                                backgroundColor: "#fff",
                                color: "#0f172a",
                            }}
                        />
                        <Legend />
                        {view === "reservations" ? (
                            <Bar
                                dataKey="confirmed"
                                name="Confirmed reservations"
                                fill="#047857"
                                radius={[4, 4, 0, 0]}
                                isAnimationActive={false}
                            />
                        ) : (
                            <>
                                <Bar
                                    dataKey="booking"
                                    name="Booking value"
                                    fill="#64748b"
                                    isAnimationActive={false}
                                />
                                <Bar
                                    dataKey="collected"
                                    name="Verified collected"
                                    fill="#047857"
                                    isAnimationActive={false}
                                />
                            </>
                        )}
                    </BarChart>
                </ResponsiveContainer>
                <details className="mt-3 text-sm">
                    <summary className="cursor-pointer">
                        View chart data
                    </summary>
                    <table className="mt-3 w-full text-left">
                        <caption className="sr-only">
                            Illustrative monthly figures
                        </caption>
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th>Confirmed</th>
                                <th>Booking value</th>
                                <th>Collected</th>
                            </tr>
                        </thead>
                        <tbody>
                            {months.map((row) => (
                                <tr key={row.month}>
                                    <th>{row.month}</th>
                                    <td>{row.confirmed}</td>
                                    <td>{formatDemoMoney(row.booking)}</td>
                                    <td>{formatDemoMoney(row.collected)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </details>
            </div>
            <div className="min-w-0 rounded-2xl border bg-white p-5 dark:bg-slate-900 dark:text-slate-100">
                <h2 className="mb-5 font-semibold">
                    Reservation status distribution
                </h2>
                <ResponsiveContainer width="100%" height={260}>
                    <BarChart
                        data={statuses}
                        layout="vertical"
                        accessibilityLayer
                    >
                        <XAxis
                            type="number"
                            allowDecimals={false}
                            stroke="#64748b"
                        />
                        <YAxis
                            type="category"
                            dataKey="name"
                            width={85}
                            stroke="#64748b"
                        />
                        <Tooltip
                            contentStyle={{
                                backgroundColor: "#fff",
                                color: "#0f172a",
                            }}
                        />
                        <Bar
                            dataKey="count"
                            name="Reservations"
                            fill="#0f766e"
                            radius={[0, 4, 4, 0]}
                            isAnimationActive={false}
                        />
                    </BarChart>
                </ResponsiveContainer>
                <p className="mt-3 text-sm text-slate-500">
                    {statuses
                        .map(
                            (status) =>
                                `${status.count} ${status.name.toLowerCase()}`,
                        )
                        .join(" · ")}
                    . Illustrative counts for the selected reporting period.
                </p>
            </div>
        </section>
    );
}
