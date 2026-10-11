import { Head, Link, router, usePage } from "@inertiajs/react";
import {
    BookOpen,
    ChefHat,
    ChevronRight,
    ClipboardList,
    History,
    LayoutDashboard,
    LogOut,
    Users,
    X,
} from "lucide-react";
import { useEffect, useState, type ReactNode } from "react";
import type { LucideIcon } from "lucide-react";
import type { SharedProps } from "../types";

type NavigationChild = {
    label: string;
    href: string;
    permission: string;
    extraPermission: string;
};
type NavigationItem = {
    label: string;
    icon: LucideIcon;
    href?: string;
    permission?: string;
    extraPermission?: string;
    children?: NavigationChild[];
};

const navigation: NavigationItem[] = [
    {
        label: "Overview",
        href: "/admin",
        permission: "dashboard.view",
        extraPermission: "dashboard.view",
        icon: LayoutDashboard,
    },
    {
        label: "Food Catalog",
        icon: ChefHat,
        children: [
            {
                label: "Food Items",
                href: "/admin/catalog/dishes",
                permission: "catalog.view",
                extraPermission: "catalog.manage",
            },
            {
                label: "Food Categories",
                href: "/admin/catalog/categories",
                permission: "catalog.view",
                extraPermission: "catalog.manage",
            },
            {
                label: "Collections & availability",
                href: "/admin/catalog/families",
                permission: "family.view",
                extraPermission: "family.manage",
            },
        ],
    },
    {
        label: "Catering Services",
        icon: ClipboardList,
        children: [
            {
                label: "Main Catering Packages",
                href: "/admin/packages/products?scope=primary",
                permission: "package.view",
                extraPermission: "package.manage",
            },
            {
                label: "Add-on Services",
                href: "/admin/packages/products?scope=addon",
                permission: "package.view",
                extraPermission: "package.manage",
            },
        ],
    },
    {
        label: "Team & Access",
        href: "/admin/staff",
        permission: "staff.view",
        extraPermission: "staff.view",
        icon: Users,
    },
    {
        label: "Activity History",
        href: "/admin/audit",
        permission: "audit.view",
        extraPermission: "audit.view",
        icon: History,
    },
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
    const [loading, setLoading] = useState(false);
    useEffect(() => {
        const start = router.on("start", () => setLoading(true));
        const finish = router.on("finish", () => setLoading(false));
        return () => {
            start();
            finish();
        };
    }, []);
    return (
        <div className="min-h-screen bg-slate-50 text-slate-900">
            <Head title={title} />
            <a
                href="#main"
                className="sr-only rounded-lg bg-white px-4 py-2 focus:not-sr-only focus:absolute focus:left-3 focus:top-3 focus:z-50"
            >
                Skip to content
            </a>
            {loading && (
                <div
                    role="progressbar"
                    aria-label="Loading page"
                    className="fixed inset-x-0 top-0 z-50 h-1 animate-pulse bg-emerald-500"
                />
            )}
            <div className="mx-auto min-h-screen max-w-[1600px] lg:grid lg:grid-cols-[248px_minmax(0,1fr)]">
                <aside className="border-b border-slate-200 bg-white lg:min-h-screen lg:border-b-0 lg:border-r">
                    <div className="flex items-center justify-between px-6 py-5 lg:py-7">
                        <Link
                            href="/admin/home"
                            className="flex items-center gap-3 text-emerald-950"
                        >
                            <span className="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-900 text-white">
                                <ChefHat size={23} aria-hidden="true" />
                            </span>
                            <span>
                                <strong className="block text-base leading-5">
                                    Zek Catering
                                </strong>
                                <small className="block text-xs font-medium tracking-wide text-slate-500">
                                    Administration
                                </small>
                            </span>
                        </Link>
                        <span className="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-600 lg:hidden">
                            {auth?.isAdmin ? "Administrator" : "Staff"}
                        </span>
                    </div>
                    <nav
                        aria-label="Administration"
                        className="flex gap-1 overflow-x-auto px-4 pb-4 lg:block lg:space-y-1 lg:px-3 lg:pt-6"
                    >
                        {navigation.map((item) => {
                            if (item.children) {
                                const children = item.children.filter(
                                    (child) =>
                                        auth?.permissions.includes(
                                            child.permission,
                                        ) ||
                                        auth?.permissions.includes(
                                            child.extraPermission,
                                        ),
                                );
                                if (!children.length) return null;
                                const Icon = item.icon;
                                return (
                                    <details
                                        key={item.label}
                                        open
                                        className="group"
                                    >
                                        <summary className="flex cursor-pointer list-none items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-100">
                                            <Icon
                                                size={18}
                                                aria-hidden="true"
                                            />
                                            {item.label}
                                        </summary>
                                        <div className="ml-5 mt-1 space-y-1 border-l border-slate-200 pl-3">
                                            {children.map((child) => (
                                                <Link
                                                    key={child.href}
                                                    href={child.href}
                                                    className={`block rounded-lg px-3 py-2 text-sm ${url.startsWith(child.href.split("?")[0]) ? "bg-emerald-100 font-semibold text-emerald-950" : "text-slate-600 hover:bg-slate-100"}`}
                                                >
                                                    {child.label}
                                                </Link>
                                            ))}
                                        </div>
                                    </details>
                                );
                            }
                            if (
                                !item.href ||
                                !(
                                    auth?.permissions.includes(
                                        item.permission ?? "",
                                    ) ||
                                    auth?.permissions.includes(
                                        item.extraPermission ?? "",
                                    )
                                )
                            )
                                return null;
                            const active =
                                item.href === "/admin"
                                    ? url === "/admin"
                                    : url.startsWith(item.href);
                            const Icon = item.icon;
                            return (
                                <Link
                                    key={item.href}
                                    href={item.href}
                                    aria-current={active ? "page" : undefined}
                                    className={`flex shrink-0 items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-colors ${active ? "bg-emerald-100 text-emerald-950" : "text-slate-600 hover:bg-slate-100 hover:text-slate-900"}`}
                                >
                                    <Icon size={18} aria-hidden="true" />
                                    {item.label}
                                </Link>
                            );
                        })}
                    </nav>
                    <div className="hidden px-4 py-6 lg:block">
                        {auth?.permissions.includes("dashboard.view") && (
                            <Link
                                href="/admin/guide"
                                className="flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-3 text-sm font-medium text-slate-600 hover:border-emerald-300 hover:text-emerald-900"
                            >
                                <BookOpen size={17} aria-hidden="true" /> Admin
                                guide
                            </Link>
                        )}
                    </div>
                </aside>
                <div className="min-w-0">
                    <header className="flex items-center justify-between gap-3 border-b border-slate-200 bg-white px-5 py-4 sm:px-8">
                        <div className="flex min-w-0 items-center gap-2 text-sm text-slate-500">
                            <span>Workspace</span>
                            <ChevronRight size={15} aria-hidden="true" />
                            <span className="truncate font-semibold text-slate-800">
                                {title}
                            </span>
                        </div>
                        <div className="flex items-center gap-3">
                            <span className="hidden text-sm font-medium text-slate-700 sm:block">
                                {auth?.user.name}
                            </span>
                            <span
                                className="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-100 text-xs font-bold text-emerald-900"
                                aria-hidden="true"
                            >
                                {auth?.user.name.slice(0, 2).toUpperCase()}
                            </span>
                            <Link
                                href="/logout"
                                method="post"
                                as="button"
                                className="inline-flex items-center gap-1 rounded-lg px-2 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-100"
                            >
                                <LogOut size={16} aria-hidden="true" />
                                <span className="hidden sm:inline">
                                    Sign out
                                </span>
                            </Link>
                        </div>
                    </header>
                    <main
                        id="main"
                        className="mx-auto max-w-6xl px-5 py-8 sm:px-8"
                    >
                        <div className="mb-7">
                            <h1 className="text-3xl font-semibold tracking-tight">
                                {title}
                            </h1>
                            {description && (
                                <p className="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                                    {description}
                                </p>
                            )}
                        </div>
                        {flash.status && (
                            <StatusToast
                                key={`${url}:${flash.status}`}
                                message={flash.status}
                            />
                        )}
                        {children}
                    </main>
                </div>
            </div>
        </div>
    );
}

function StatusToast({ message }: { message: string }) {
    const [visible, setVisible] = useState(true);
    if (!visible) return null;
    return (
        <div
            role="status"
            className="mb-6 flex items-start justify-between gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-950"
        >
            <span>{message}</span>
            <button
                type="button"
                aria-label="Dismiss notification"
                onClick={() => setVisible(false)}
            >
                <X size={16} aria-hidden="true" />
            </button>
        </div>
    );
}
