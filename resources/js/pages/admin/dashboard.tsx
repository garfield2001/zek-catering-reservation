import { Link, usePage } from "@inertiajs/react";
import {
    ArrowRight,
    BookOpen,
    Boxes,
    ChefHat,
    CircleCheck,
    ClipboardList,
    Layers3,
    Users,
} from "lucide-react";
import type { ReactNode } from "react";
import AdminLayout from "../../layouts/admin-layout";
import type { SharedProps } from "../../types";

type Setup = {
    hasPrimary: boolean;
    collections: number;
    categories: number;
    items: number;
    offerings: number;
    products: number;
    variants: number;
};

export default function Dashboard({ setup }: { setup: Setup }) {
    const { auth } = usePage<SharedProps>().props;
    const canCatalog =
        auth?.permissions.includes("catalog.view") ||
        auth?.permissions.includes("catalog.manage");
    const canCollections =
        auth?.permissions.includes("family.view") ||
        auth?.permissions.includes("family.manage");
    const canPackages =
        auth?.permissions.includes("package.view") ||
        auth?.permissions.includes("package.manage");
    const steps = [
        {
            label: "Create primary catering collection",
            detail: "Name your primary catering collection.",
            done: setup.hasPrimary,
            href: "/admin/catalog/families",
            allowed: canCollections,
        },
        {
            label: "Add food categories",
            detail: "Organize the culinary catalog.",
            done: setup.categories > 0,
            href: "/admin/catalog/categories",
            allowed: canCatalog,
        },
        {
            label: "Add food items",
            detail: "Choose which collections may offer each item.",
            done: setup.items > 0,
            href: "/admin/catalog/dishes",
            allowed: canCatalog,
        },
        {
            label: "Configure offerings",
            detail: "Add collection-specific preparations and serving options.",
            done: setup.offerings > 0,
            href: "/admin/catalog/dishes",
            allowed: canCatalog,
        },
        {
            label: "Build package drafts",
            detail: "Create products, variants, inclusions and prices.",
            done: setup.variants > 0,
            href: "/admin/packages/products",
            allowed: canPackages,
        },
    ];
    return (
        <AdminLayout
            title="Overview"
            description="Your catering administration workspace."
        >
            <section className="overflow-hidden rounded-2xl bg-gradient-to-br from-emerald-950 via-emerald-900 to-teal-800 p-7 text-white shadow-lg">
                <p className="text-xs font-semibold uppercase tracking-[.2em] text-emerald-200">
                    {auth?.isAdmin
                        ? "Administrator workspace"
                        : "Staff workspace"}
                </p>
                <h2 className="mt-3 text-2xl font-semibold">
                    Welcome, {auth?.user.name}
                </h2>
                <p className="mt-2 max-w-2xl text-sm leading-6 text-emerald-100">
                    {setup.collections === 0
                        ? "Your catalog is ready to set up. No commercial products are preloaded."
                        : "Manage your collections, food catalog and unpublished package configurations."}{" "}
                    Available tools reflect your assigned permissions.
                </p>
                {canCollections && (
                    <Link
                        href="/admin/catalog/families"
                        className="mt-5 inline-flex items-center gap-2 rounded-lg bg-white px-4 py-2.5 text-sm font-semibold text-emerald-950 hover:bg-emerald-50"
                    >
                        Open collections{" "}
                        <ArrowRight size={16} aria-hidden="true" />
                    </Link>
                )}
            </section>
            <div className="mt-6 grid gap-4 sm:grid-cols-3">
                <Metric
                    icon={<Layers3 size={20} />}
                    label="Collections"
                    value={setup.collections}
                />
                <Metric
                    icon={<ChefHat size={20} />}
                    label="Food items"
                    value={setup.items}
                />
                <Metric
                    icon={<Boxes size={20} />}
                    label="Draft variants"
                    value={setup.variants}
                />
            </div>
            {auth?.isAdmin && (
                <section className="mt-8 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 className="text-lg font-semibold">
                                Business setup
                            </h2>
                            <p className="mt-1 text-sm text-slate-600">
                                A practical path from an empty installation to a
                                reviewable draft catalog.
                            </p>
                        </div>
                        <Link
                            href="/admin/guide"
                            className="inline-flex items-center gap-2 text-sm font-semibold text-emerald-800 hover:underline"
                        >
                            <BookOpen size={16} aria-hidden="true" /> Open guide
                        </Link>
                    </div>
                    <ol className="mt-5 grid gap-3 md:grid-cols-2">
                        {steps
                            .filter((step) => step.allowed)
                            .map((step, index) => (
                                <li key={step.label}>
                                    <Link
                                        href={step.href}
                                        className="flex items-start gap-3 rounded-xl border border-slate-200 p-4 hover:border-emerald-400 hover:bg-emerald-50/40"
                                    >
                                        <span
                                            className={`flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-bold ${step.done ? "bg-emerald-100 text-emerald-800" : "bg-slate-100 text-slate-600"}`}
                                        >
                                            {step.done ? (
                                                <CircleCheck
                                                    size={19}
                                                    aria-label="Complete"
                                                />
                                            ) : (
                                                index + 1
                                            )}
                                        </span>
                                        <span>
                                            <strong className="block text-sm">
                                                {step.label}
                                            </strong>
                                            <span className="mt-1 block text-xs leading-5 text-slate-600">
                                                {step.detail}
                                            </span>
                                        </span>
                                        <ArrowRight
                                            size={16}
                                            className="ml-auto shrink-0 text-slate-400"
                                            aria-hidden="true"
                                        />
                                    </Link>
                                </li>
                            ))}
                    </ol>
                </section>
            )}
            <div className="mt-6 grid gap-4 sm:grid-cols-2">
                {canPackages && (
                    <QuickLink
                        icon={<ClipboardList size={20} />}
                        href="/admin/packages/products"
                        title="Package drafts"
                        description="Review configured variants and server-calculated draft previews."
                    />
                )}
                {auth?.permissions.includes("staff.view") && (
                    <QuickLink
                        icon={<Users size={20} />}
                        href="/admin/staff"
                        title="Team & access"
                        description="Manage accounts and assigned permissions."
                    />
                )}
            </div>
        </AdminLayout>
    );
}

function Metric({
    icon,
    label,
    value,
}: {
    icon: ReactNode;
    label: string;
    value: number;
}) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="flex items-center gap-3 text-slate-500">
                {icon}
                <span className="text-sm font-medium">{label}</span>
            </div>
            <p className="mt-3 text-3xl font-semibold text-slate-900">
                {value}
            </p>
        </div>
    );
}

function QuickLink({
    icon,
    href,
    title,
    description,
}: {
    icon: ReactNode;
    href: string;
    title: string;
    description: string;
}) {
    return (
        <Link
            href={href}
            className="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm hover:border-emerald-400"
        >
            <div className="flex items-center gap-3 font-semibold text-slate-900">
                {icon}
                {title}
                <ArrowRight
                    size={16}
                    className="ml-auto text-emerald-700 transition-transform group-hover:translate-x-1"
                    aria-hidden="true"
                />
            </div>
            <p className="mt-2 text-sm text-slate-600">{description}</p>
        </Link>
    );
}
