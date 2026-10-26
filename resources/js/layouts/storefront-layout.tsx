import { Head, Link, usePage } from "@inertiajs/react";
import { Menu, ShoppingBag, X } from "lucide-react";
import { useState, type ReactNode } from "react";

const links = [
    { href: "/", label: "Home" },
    { href: "/catering-packages", label: "Catering Packages" },
    { href: "/add-on-services", label: "Add-on Services" },
];

export default function StorefrontLayout({
    title,
    children,
    selectionCount = 0,
}: {
    title: string;
    children: ReactNode;
    selectionCount?: number;
}) {
    const { url } = usePage();
    const [open, setOpen] = useState(false);

    return (
        <div className="min-h-screen bg-stone-50 text-stone-900">
            <Head title={title} />
            <a
                className="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:m-3 focus:rounded bg-white focus:p-3"
                href="#main"
            >
                Skip to content
            </a>
            <header className="border-b border-stone-200 bg-white">
                <div className="mx-auto flex min-h-20 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
                    <Link
                        href="/"
                        className="font-serif text-xl font-bold tracking-tight text-emerald-950"
                    >
                        ZEK{" "}
                        <span className="font-sans text-sm font-medium tracking-wide text-amber-700">
                            CATERING
                        </span>
                    </Link>
                    <nav
                        className="hidden items-center gap-6 text-sm font-medium md:flex"
                        aria-label="Customer navigation"
                    >
                        {links.map((link) => (
                            <Link
                                key={link.href}
                                href={link.href}
                                className={
                                    url === link.href
                                        ? "text-emerald-800"
                                        : "text-stone-600 hover:text-emerald-800"
                                }
                            >
                                {link.label}
                            </Link>
                        ))}
                    </nav>
                    <div className="flex items-center gap-2">
                        <Link
                            href="/my-selection"
                            className="inline-flex items-center gap-2 rounded-full border border-emerald-800 px-3 py-2 text-sm font-semibold text-emerald-900 hover:bg-emerald-50"
                            aria-label={`My selection, ${selectionCount} items`}
                        >
                            <ShoppingBag size={17} aria-hidden="true" />
                            <span className="hidden sm:inline">
                                My Selection
                            </span>
                            <span className="rounded-full bg-emerald-800 px-1.5 text-xs text-white">
                                {selectionCount}
                            </span>
                        </Link>
                        <button
                            className="rounded-lg p-2 md:hidden"
                            type="button"
                            aria-label={
                                open ? "Close navigation" : "Open navigation"
                            }
                            onClick={() => setOpen(!open)}
                        >
                            {open ? <X /> : <Menu />}
                        </button>
                    </div>
                </div>
                {open && (
                    <nav
                        className="border-t border-stone-200 px-4 py-3 md:hidden"
                        aria-label="Customer navigation"
                    >
                        {links.map((link) => (
                            <Link
                                key={link.href}
                                href={link.href}
                                onClick={() => setOpen(false)}
                                className="block rounded-lg px-3 py-3 text-sm font-medium text-stone-700 hover:bg-stone-100"
                            >
                                {link.label}
                            </Link>
                        ))}
                    </nav>
                )}
            </header>
            <main id="main">{children}</main>
            <footer className="mt-16 border-t border-stone-200 bg-white">
                <div className="mx-auto max-w-7xl px-4 py-10 text-sm text-stone-600 sm:px-6 lg:px-8">
                    <strong className="text-emerald-950">ZEK Catering</strong>
                    <p className="mt-2">
                        Browse packages and build a selection for planning.
                        Availability and final arrangements are confirmed with
                        our team.
                    </p>
                </div>
            </footer>
        </div>
    );
}
