import { Link, usePage } from "@inertiajs/react";
import AdminLayout from "../../layouts/admin-layout";
import type { SharedProps } from "../../types";

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
    const steps = [
        {
            title: "Food Catalog",
            detail: "Organize categories, add foods and choose where they are available.",
            href: "/admin/food-catalog",
            allowed: auth?.permissions.some((p) =>
                ["catalog.view", "catalog.manage"].includes(p),
            ),
        },
        {
            title: "Catering Packages",
            detail: "Create a package, configure its menu and price, then review and publish.",
            href: "/admin/catering-packages",
            allowed: auth?.permissions.some((p) =>
                ["package.view", "package.manage"].includes(p),
            ),
        },
        {
            title: "Add-on Services",
            detail: "Set up optional services and their own foods, serving sizes and packages.",
            href: "/admin/add-on-services",
            allowed: auth?.permissions.some((p) =>
                ["family.view", "family.manage"].includes(p),
            ),
        },
    ];
    return (
        <AdminLayout
            title="Dashboard"
            description="Your catering administration workspace."
        >
            <section className="rounded-2xl bg-emerald-950 p-8 text-white">
                <p className="text-sm text-emerald-200">
                    {auth?.isAdmin ? "Administrator" : "Staff"} workspace
                </p>
                <h2 className="mt-2 text-2xl font-semibold">
                    Welcome, {auth?.user.name}
                </h2>
                <p className="mt-3 text-sm text-emerald-100">
                    Prepare your foods, build your packages and publish when
                    ready.
                </p>
            </section>
            <div className="my-6 grid gap-4 sm:grid-cols-3">
                {[
                    ["Categories", setup.categories],
                    ["Food Items", setup.items],
                    ["Packages", setup.products],
                ]
                    .filter(([, value]) => value !== null)
                    .map(([label, value]) => (
                        <div
                            key={label}
                            className="rounded-xl border bg-white p-5"
                        >
                            <p className="text-sm text-slate-500">{label}</p>
                            <p className="mt-2 text-3xl font-semibold">
                                {value}
                            </p>
                        </div>
                    ))}
            </div>
            <div className="grid gap-5 md:grid-cols-3">
                {steps
                    .filter((s) => s.allowed)
                    .map((step) => (
                        <Link
                            key={step.href}
                            href={step.href}
                            className="rounded-xl border bg-white p-6 hover:border-emerald-700"
                        >
                            <h2 className="font-semibold text-emerald-900">
                                {step.title} →
                            </h2>
                            <p className="mt-3 text-sm text-slate-600">
                                {step.detail}
                            </p>
                        </Link>
                    ))}
            </div>
            <Link
                href="/admin/guide"
                className="mt-6 inline-block text-sm text-emerald-800 underline"
            >
                Owner setup guide
            </Link>
        </AdminLayout>
    );
}
