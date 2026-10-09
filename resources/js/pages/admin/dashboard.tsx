import { Link, usePage } from "@inertiajs/react";
import AdminLayout from "../../layouts/admin-layout";
import type { SharedProps } from "../../types";
export default function Dashboard() {
    const { auth } = usePage<SharedProps>().props;
    return (
        <AdminLayout
            title="Overview"
            description="Your team’s administration workspace."
        >
            <section className="rounded-xl border border-stone-200 bg-white p-7">
                <p className="text-xs font-semibold uppercase tracking-widest text-emerald-800">
                    {auth?.isAdmin ? "Administrator" : "Staff workspace"}
                </p>
                <h2 className="mt-3 text-xl font-medium">
                    Welcome, {auth?.user.name}
                </h2>
                <p className="mt-2 text-stone-600">
                    Your access is managed by your administrator. Available
                    tools appear in the navigation.
                </p>
            </section>
            <div className="mt-6 grid gap-4 sm:grid-cols-2">
                {(auth?.permissions.includes("catalog.view") ||
                    auth?.permissions.includes("catalog.manage")) && (
                    <Link
                        href="/admin/catalog/dishes"
                        className="rounded-xl border border-stone-200 bg-white p-6 hover:border-emerald-700"
                    >
                        <h2 className="font-semibold">Master catalog →</h2>
                        <p className="mt-2 text-sm text-stone-600">
                            Manage categories, canonical dishes and
                            family-specific offerings.
                        </p>
                    </Link>
                )}
                {auth?.permissions.includes("staff.view") && (
                    <Link
                        href="/admin/staff"
                        className="rounded-xl border border-stone-200 bg-white p-6 hover:border-emerald-700"
                    >
                        <h2 className="font-semibold">Team & access →</h2>
                        <p className="mt-2 text-sm text-stone-600">
                            Manage accounts and review assigned permissions.
                        </p>
                    </Link>
                )}
                {auth?.permissions.includes("audit.view") && (
                    <Link
                        href="/admin/audit"
                        className="rounded-xl border border-stone-200 bg-white p-6 hover:border-emerald-700"
                    >
                        <h2 className="font-semibold">Audit history →</h2>
                        <p className="mt-2 text-sm text-stone-600">
                            Review account changes and sign-in activity.
                        </p>
                    </Link>
                )}
            </div>
        </AdminLayout>
    );
}
