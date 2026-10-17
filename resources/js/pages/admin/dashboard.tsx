import { Link, usePage } from "@inertiajs/react";
import { useState } from "react";
import AdminLayout from "../../layouts/admin-layout";
import type { SharedProps } from "../../types";
import {
    demoAsOf,
    demoMonths,
    demoSummary,
} from "../../features/dashboard/demo-data";
import {
    OperationsOverview,
    FinancialActivity,
} from "../../features/dashboard/components/operations-overview";
import { OperationsSchedule } from "../../features/dashboard/components/operations-schedule";
import { OperationsCharts } from "../../features/dashboard/components/operations-charts";

export default function Dashboard({
    setup,
}: {
    setup: {
        categories: number | null;
        items: number | null;
        products: number | null;
    };
}) {
    const { auth } = usePage<SharedProps>().props;
    const [period, setPeriod] = useState(3);
    const months = demoMonths.slice(-period);
    const totals = demoSummary(months);
    const shortcuts = [
        {
            title: "Food Catalog",
            href: "/admin/food-catalog",
            permissions: ["catalog.view", "catalog.manage"],
        },
        {
            title: "Catering Packages",
            href: "/admin/catering-packages",
            permissions: ["package.view", "package.manage"],
        },
        {
            title: "Add-on Services",
            href: "/admin/add-on-services",
            permissions: ["family.view", "family.manage"],
        },
    ];
    return (
        <AdminLayout
            title="Dashboard"
            description="Catering operations · Visual preview"
        >
            <div className="space-y-5">
                <header className="flex flex-wrap items-end justify-between gap-4 rounded-2xl bg-emerald-950 p-6 text-white">
                    <div>
                        <span className="rounded-full border border-emerald-500 px-3 py-1 text-xs font-semibold">
                            DEMO DATA / PREVIEW
                        </span>
                        <h2 className="mt-4 text-2xl font-semibold">
                            Your catering day, at a glance
                        </h2>
                        <p className="mt-2 max-w-2xl text-sm text-emerald-100">
                            All operational figures below are static examples as
                            of {demoAsOf}. Inquiry, reservation and payment
                            workflows are not implemented.
                        </p>
                    </div>
                    <label className="text-sm">
                        Demo reporting period
                        <select
                            className="mt-1 text-slate-900"
                            value={period}
                            onChange={(event) =>
                                setPeriod(Number(event.target.value))
                            }
                        >
                            <option value={3}>August–October 2026</option>
                            <option value={6}>May–October 2026</option>
                        </select>
                    </label>
                </header>
                <OperationsOverview totals={totals} />
                <OperationsSchedule />
                <FinancialActivity totals={totals} />
                <OperationsCharts months={months} />
                <section className="rounded-2xl border bg-white p-5 dark:bg-slate-900 dark:text-slate-100">
                    <h2 className="font-semibold">Quick actions</h2>
                    <p className="mt-1 text-sm text-slate-500">
                        Catalog links open real workspaces. Future operations
                        are not yet available.
                    </p>
                    <div className="mt-4 flex flex-wrap gap-3">
                        {shortcuts
                            .filter((shortcut) =>
                                auth?.permissions.some((permission) =>
                                    shortcut.permissions.includes(permission),
                                ),
                            )
                            .map((shortcut) => (
                                <Link
                                    key={shortcut.href}
                                    className="rounded-lg border px-4 py-2 text-sm font-medium text-emerald-800 dark:text-emerald-300"
                                    href={shortcut.href}
                                >
                                    {shortcut.title}
                                </Link>
                            ))}
                        {[
                            "New inquiry",
                            "Prepare quotation",
                            "Verify payment",
                            "New reservation",
                        ].map((label) => (
                            <span
                                key={label}
                                className="rounded-lg border border-dashed p-3"
                            >
                                <button
                                    disabled
                                    className="text-sm text-slate-500"
                                >
                                    {label}
                                </button>
                                <span className="block text-xs text-slate-500">
                                    Coming soon · workflow not implemented
                                </span>
                            </span>
                        ))}
                    </div>
                </section>
                <details className="rounded-2xl border bg-white p-5 dark:bg-slate-900 dark:text-slate-100">
                    <summary className="cursor-pointer font-semibold">
                        Real catalog setup · not demo data
                    </summary>
                    <div className="mt-3 flex flex-wrap gap-5">
                        {Object.entries(setup)
                            .filter(([, value]) => value !== null)
                            .map(([label, value]) => (
                                <p key={label} className="text-sm capitalize">
                                    {label}: <strong>{value}</strong>
                                </p>
                            ))}
                    </div>
                    <Link
                        href="/admin/guide"
                        className="mt-3 inline-block text-sm text-emerald-800 underline"
                    >
                        Owner setup guide
                    </Link>
                </details>
            </div>
        </AdminLayout>
    );
}
