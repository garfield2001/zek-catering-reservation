import { Head, Link, router, usePage } from "@inertiajs/react";
import {
    ChefHat,
    ClipboardList,
    History,
    LayoutDashboard,
    LogOut,
    Users,
    Boxes,
    Menu,
    ChevronDown,
} from "lucide-react";
import { useEffect, useState, type ReactNode } from "react";
import * as DropdownMenu from "@radix-ui/react-dropdown-menu";
import { Modal } from "../components/ui/modal";
import { UserAvatar } from "../components/ui/user-avatar";
import { useFlashToast } from "../components/ui/notifications";
import type { SharedProps } from "../types";

const navigation = [
    {
        label: "Dashboard",
        href: "/admin",
        permissions: ["dashboard.view"],
        icon: LayoutDashboard,
    },
    {
        label: "Food Catalog",
        href: "/admin/food-catalog",
        permissions: ["catalog.view", "catalog.manage"],
        icon: ChefHat,
    },
    {
        label: "Catering Packages",
        href: "/admin/catering-packages",
        permissions: ["package.view", "package.manage"],
        icon: ClipboardList,
    },
    {
        label: "Add-on Services",
        href: "/admin/add-on-services",
        permissions: ["family.view", "family.manage"],
        icon: Boxes,
    },
    {
        label: "Team & Access",
        href: "/admin/staff",
        permissions: ["staff.view"],
        icon: Users,
    },
    {
        label: "Activity History",
        href: "/admin/audit",
        permissions: ["audit.view"],
        icon: History,
    },
];

export default function AdminLayout({
    title,
    description,
    children,
    section,
}: {
    title: string;
    description?: string;
    children: ReactNode;
    section?: "catalog" | "catering" | "services";
}) {
    const { auth, flash, product } = usePage<
        SharedProps & {
            product?: {
                id: number;
                name: string;
                family?: { id: number; name: string; is_addon: boolean };
            };
        }
    >().props;
    const { url } = usePage();
    useFlashToast(flash);
    const [mobile, setMobile] = useState(false);
    const [loading, setLoading] = useState(false);
    useEffect(() => {
        const start = router.on("start", () => setLoading(true));
        const finish = router.on("finish", () => setLoading(false));
        return () => {
            start();
            finish();
        };
    }, []);
    const path = url.split("?")[0];
    const activeSection =
        section ??
        (path.startsWith("/admin/catalog/dishes") ||
        path.startsWith("/admin/catalog/categories")
            ? "catalog"
            : path.startsWith("/admin/packages")
              ? product?.family?.is_addon
                  ? "services"
                  : "catering"
              : undefined);
    const sectionPath =
        activeSection === "catalog"
            ? "/admin/food-catalog"
            : activeSection === "services"
              ? "/admin/add-on-services"
              : activeSection === "catering"
                ? "/admin/catering-packages"
                : path;
    const parent = navigation.find(
        (item) => item.href !== "/admin" && sectionPath.startsWith(item.href),
    );
    const navItems = navigation
        .filter((item) =>
            item.permissions.some((permission) =>
                auth?.permissions.includes(permission),
            ),
        )
        .map((item) => {
            const active =
                item.href === "/admin"
                    ? path === "/admin"
                    : sectionPath.startsWith(item.href);
            return (
                <Link
                    key={item.href}
                    href={item.href}
                    onClick={() => setMobile(false)}
                    aria-current={active ? "page" : undefined}
                    className={`flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-medium ${active ? "bg-emerald-50 text-emerald-900 ring-1 ring-inset ring-emerald-100" : "text-slate-600 hover:bg-slate-50"}`}
                >
                    <item.icon size={19} aria-hidden="true" />
                    {item.label}
                </Link>
            );
        });
    return (
        <div className="min-h-screen bg-slate-50 text-slate-900">
            <Head title={title} />
            <a
                href="#main"
                className="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:bg-white focus:p-4"
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
            <div className="mx-auto min-h-screen max-w-[1600px] lg:grid lg:grid-cols-[240px_minmax(0,1fr)]">
                <aside className="hidden border-r border-slate-200 bg-white lg:sticky lg:top-0 lg:block lg:h-screen">
                    <Link
                        href="/admin/home"
                        className="flex items-center gap-3 px-6 py-7 text-emerald-950"
                    >
                        <ChefHat size={30} />
                        <span>
                            <strong className="block">Zek Catering</strong>
                            <small className="text-slate-500">
                                Administration
                            </small>
                        </span>
                    </Link>
                    <p className="px-6 pb-3 text-[11px] font-semibold uppercase tracking-widest text-slate-400">
                        Workspace
                    </p>
                    <nav aria-label="Administration" className="space-y-1 px-3">
                        {navItems}
                    </nav>
                </aside>
                <div className="min-w-0">
                    <header className="sticky top-0 z-30 flex min-h-16 items-center justify-between gap-3 border-b bg-white/95 px-4 py-3 backdrop-blur sm:px-8">
                        <button
                            aria-label="Open navigation"
                            className="rounded-lg p-2 lg:hidden"
                            onClick={() => setMobile(true)}
                        >
                            <Menu size={22} />
                        </button>
                        <nav
                            aria-label="Breadcrumb"
                            className="min-w-0 flex-1 truncate text-sm text-slate-500"
                        >
                            {parent && title !== parent.label ? (
                                <>
                                    <Link
                                        href={parent.href}
                                        className="text-emerald-800 hover:underline"
                                    >
                                        {parent.label}
                                    </Link>
                                    <span aria-hidden="true"> / </span>
                                </>
                            ) : null}
                            {product?.family?.is_addon && (
                                <>
                                    <Link
                                        href={`/admin/add-on-services/${product.family.id}`}
                                        className="text-emerald-800 hover:underline"
                                    >
                                        {product.family.name}
                                    </Link>
                                    <span aria-hidden="true"> / </span>
                                </>
                            )}
                            {product && title !== product.name && (
                                <>
                                    <Link
                                        href={`/admin/packages/products/${product.id}`}
                                        className="text-emerald-800 hover:underline"
                                    >
                                        {product.name}
                                    </Link>
                                    <span aria-hidden="true"> / </span>
                                </>
                            )}
                            <span className="font-medium text-slate-800">
                                {title}
                            </span>
                        </nav>
                        <DropdownMenu.Root>
                            <DropdownMenu.Trigger
                                className="flex shrink-0 items-center gap-2 rounded-lg px-2 py-2 text-sm hover:bg-slate-100"
                                aria-label="Account menu"
                            >
                                <UserAvatar
                                    name={auth?.user.name ?? "User"}
                                    src={auth?.user.avatar_url}
                                    size="sm"
                                />
                                <span className="hidden max-w-40 truncate sm:block">
                                    {auth?.user.name}
                                </span>
                                <ChevronDown size={14} />
                            </DropdownMenu.Trigger>
                            <DropdownMenu.Portal>
                                <DropdownMenu.Content
                                    sideOffset={8}
                                    align="end"
                                    className="z-50 min-w-52 rounded-xl border bg-white p-2 shadow-lg"
                                >
                                    <DropdownMenu.Label className="px-3 py-2 text-xs text-slate-500">
                                        {auth?.isAdmin
                                            ? "Administrator"
                                            : "Staff"}
                                    </DropdownMenu.Label>
                                    <DropdownMenu.Separator className="my-1 h-px bg-slate-100" />
                                    <DropdownMenu.Item asChild>
                                        <Link
                                            href="/admin/profile"
                                            className="flex cursor-pointer items-center gap-2 rounded-lg px-3 py-2 text-sm outline-none data-[highlighted]:bg-slate-100"
                                        >
                                            Profile photo
                                        </Link>
                                    </DropdownMenu.Item>
                                    <DropdownMenu.Item
                                        onSelect={() => router.post("/logout")}
                                        className="flex cursor-pointer items-center gap-2 rounded-lg px-3 py-2 text-sm outline-none data-[highlighted]:bg-slate-100"
                                    >
                                        <LogOut size={16} />
                                        Sign out
                                    </DropdownMenu.Item>
                                </DropdownMenu.Content>
                            </DropdownMenu.Portal>
                        </DropdownMenu.Root>
                    </header>
                    <main
                        id="main"
                        className="mx-auto max-w-6xl px-4 py-6 sm:px-8 sm:py-8"
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
                        {children}
                    </main>
                </div>
            </div>
            <Modal
                open={mobile}
                onClose={() => setMobile(false)}
                title="Zek Catering"
                sheet
            >
                <nav aria-label="Mobile administration" className="space-y-1">
                    {navItems}
                </nav>
            </Modal>
        </div>
    );
}
