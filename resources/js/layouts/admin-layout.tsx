import { Head, Link, usePage } from "@inertiajs/react";
import type { ReactNode } from "react";
import type { SharedProps } from "../types";
const navigation: {
    label: string;
    href: string;
    permission: string;
    extraPermission?: string;
    adminOnly?: boolean;
}[] = [
    { label: "Overview", href: "/admin", permission: "dashboard.view" },
    {
        label: "Dish categories",
        href: "/admin/catalog/categories",
        permission: "catalog.view",
        extraPermission: "catalog.manage",
    },
    {
        label: "Master dishes",
        href: "/admin/catalog/dishes",
        permission: "catalog.view",
        extraPermission: "catalog.manage",
    },
    {
        label: "Ordering defaults",
        href: "/admin/catalog/families",
        permission: "catalog.manage",
        adminOnly: true,
    },
    { label: "Team & access", href: "/admin/staff", permission: "staff.view" },
    { label: "Audit history", href: "/admin/audit", permission: "audit.view" },
];
export default function AdminLayout({
    title,
    description,
    children,
}: {
    title: string;
    description?: string;
    children: ReactNode;
}) {
    const { auth, flash } = usePage<SharedProps>().props;
    const { url } = usePage();
    return (
        <div className="min-h-screen">
            <Head title={title} />
            <a href="#main" className="sr-only focus:not-sr-only">
                Skip to content
            </a>
            <header className="border-b border-stone-200 bg-white">
                <div className="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-6 py-5">
                    <Link
                        href="/admin"
                        className="text-xl font-bold tracking-tight text-emerald-900"
                    >
                        Zek Catering{" "}
                        <span className="ml-2 text-xs font-normal uppercase tracking-widest text-stone-500">
                            Administration
                        </span>
                    </Link>
                    <div className="flex items-center gap-4 text-sm">
                        <span>{auth?.user.name}</span>
                        <Link
                            href="/logout"
                            method="post"
                            as="button"
                            className="underline"
                        >
                            Sign out
                        </Link>
                    </div>
                </div>
            </header>
            <div className="mx-auto grid max-w-6xl gap-8 px-6 py-8 md:grid-cols-[180px_1fr]">
                <nav
                    aria-label="Administration"
                    className="flex gap-2 md:flex-col"
                >
                    {navigation
                        .filter(
                            (item) =>
                                (!item.adminOnly || auth?.isAdmin) &&
                                (auth?.permissions.includes(item.permission) ||
                                    (item.extraPermission &&
                                        auth?.permissions.includes(
                                            item.extraPermission,
                                        ))),
                        )
                        .map((item) => (
                            <Link
                                key={item.href}
                                href={item.href}
                                aria-current={
                                    url === item.href ? "page" : undefined
                                }
                                className={
                                    "rounded-lg px-3 py-2 text-sm font-medium " +
                                    (url === item.href
                                        ? "bg-emerald-100 text-emerald-950"
                                        : "hover:bg-stone-100")
                                }
                            >
                                {item.label}
                            </Link>
                        ))}
                </nav>
                <main id="main" className="min-w-0">
                    <div className="mb-7">
                        <h1 className="text-3xl font-semibold tracking-tight">
                            {title}
                        </h1>
                        {description && (
                            <p className="mt-2 text-stone-600">{description}</p>
                        )}
                    </div>
                    {flash.status && (
                        <div
                            role="status"
                            className="mb-5 rounded-lg bg-emerald-100 p-4 text-sm text-emerald-950"
                        >
                            {flash.status}
                        </div>
                    )}
                    {children}
                </main>
            </div>
        </div>
    );
}
