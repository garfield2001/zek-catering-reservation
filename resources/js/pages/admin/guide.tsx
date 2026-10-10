import { Link } from "@inertiajs/react";
import { BookOpen, Info } from "lucide-react";
import AdminLayout from "../../layouts/admin-layout";

const sections = [
    {
        title: "1. Create your primary catering collection",
        href: "/admin/catalog/families",
        body: "A new installation has no preloaded business offerings. Create one primary collection, choose its display name, then configure the inherited 150-pax minimum if your approved policy changes.",
    },
    {
        title: "2. Add food categories and food items",
        href: "/admin/catalog/categories",
        body: "Create culinary categories, then one canonical record per food or beverage. Assign each item only to the collections where it may be offered. The same item can be shared without duplication.",
    },
    {
        title: "3. Configure offerings and serving options",
        href: "/admin/catalog/dishes",
        body: "From a food item, add collection-specific preparations and serving units such as trays, portions, packs or kilograms. Mark unavailable records inactive; their history stays linked.",
    },
    {
        title: "4. Build primary package variants",
        href: "/admin/packages/products",
        body: "Create a package product in your primary catering collection, then as many named variants as needed. Each draft has independent base price, quantity overrides, included items or customer choices, allowed serving options and food category/item rules.",
    },
    {
        title: "5. Create add-on collections",
        href: "/admin/catalog/families",
        body: "Create any number of add-ons. Choose package-based, packed-meal, fixed-bundle or direct-sale behavior. Two collections may use the same behavior but have separate food eligibility and offerings.",
    },
    {
        title: "6. Configure and preview drafts",
        href: "/admin/packages/products",
        body: "Package, packed-meal and fixed-bundle add-ons use the package builder. Packed meals may enable split allocations; fixed bundles only allow fixed inclusions. Preview prices are calculated by Laravel in centavos and are not bookable quotations.",
    },
    {
        title: "7. Manage Staff and review activity",
        href: "/admin/staff",
        body: "Staff starts with minimal access. Grant specific collection, catalog, package and price permissions through Team & access. Review successful changes in Activity History.",
    },
];

export default function Guide() {
    return (
        <AdminLayout
            title="Admin guide"
            description="A practical setup path for one catering business. Only configured draft features are available now."
        >
            <div className="mb-6 flex gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-5 text-emerald-950">
                <BookOpen className="shrink-0" size={22} aria-hidden="true" />
                <p className="text-sm leading-6">
                    Create business data through the interface; no sample
                    packages or prices are installed for you. Codes are stable
                    internal identities, while names are customer-facing and
                    editable.
                </p>
            </div>
            <div className="space-y-4">
                {sections.map((section) => (
                    <section
                        key={section.title}
                        className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
                    >
                        <h2 className="font-semibold">{section.title}</h2>
                        <p className="mt-2 text-sm leading-6 text-slate-600">
                            {section.body}
                        </p>
                        <Link
                            href={section.href}
                            className="mt-3 inline-block text-sm font-semibold text-emerald-800 hover:underline"
                        >
                            Open this area →
                        </Link>
                    </section>
                ))}
            </div>
            <div className="mt-7 flex gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-5 text-amber-950">
                <Info size={21} className="shrink-0" aria-hidden="true" />
                <p className="text-sm leading-6">
                    <strong>Deferred:</strong> Direct-sale add-ons can be
                    identified and offered in the master catalog, but their
                    priced listing manager, publication, customer browsing,
                    checkout, inquiries and reservations are not implemented in
                    Phase 3.5. Draft previews do not create sales or bookings.
                </p>
            </div>
        </AdminLayout>
    );
}
