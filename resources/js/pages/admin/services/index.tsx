import { Link } from "@inertiajs/react";
import { useLiveFilters } from "../../../features/catalog/use-live-filters";
import AdminLayout from "../../../layouts/admin-layout";
import { serviceBehaviors } from "../../../features/catalog/service-behaviors";
import type { Collection } from "../../../features/catalog/types";
import { PaginationControls } from "../../../components/ui/pagination";
export default function ServicesIndex({
    services,
    canManage,
    filters,
}: {
    services: {
        data: Collection[];
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    canManage: boolean;
    filters: { q?: string; archived?: boolean };
}) {
    const search = useLiveFilters("/admin/add-on-services", {
        q: filters.q ?? "",
        archived: filters.archived ? "1" : "0",
    });
    return (
        <AdminLayout
            title="Add-on Services"
            description="Build optional catering services with their own foods, packages and ordering rules."
            section="services"
        >
            {canManage && (
                <Link
                    href="/admin/add-on-services/create"
                    className="inline-flex rounded-lg bg-emerald-800 px-4 py-2 font-semibold text-white hover:bg-emerald-900"
                >
                    Create Add-on Service
                </Link>
            )}
            <div className="my-5 flex flex-wrap items-center gap-3">
                <input
                    className="rounded-lg border bg-white p-2"
                    name="q"
                    aria-label="Search services"
                    placeholder="Search services"
                    value={search.filters.q}
                    onChange={(event) =>
                        search.setFilters((current) => ({
                            ...current,
                            q: event.target.value,
                        }))
                    }
                />
                <label className="text-sm">
                    <input
                        name="archived"
                        type="checkbox"
                        value="1"
                        checked={search.filters.archived === "1"}
                        onChange={(event) =>
                            search.setFilters((current) => ({
                                ...current,
                                archived: event.target.checked ? "1" : "0",
                            }))
                        }
                    />{" "}
                    Include archived
                </label>
                {search.filters.q && (
                    <button
                        type="button"
                        className="text-sm underline"
                        onClick={() =>
                            search.setFilters((current) => ({
                                ...current,
                                q: "",
                            }))
                        }
                    >
                        Clear search
                    </button>
                )}
                <span role="status" className="text-sm text-slate-500">
                    {search.pending ? "Updating results…" : ""}
                </span>
            </div>
            <div className="grid grid-cols-[repeat(auto-fill,minmax(min(100%,18rem),24rem))] gap-4">
                {services.data.map((service) => (
                    <article
                        className="rounded-2xl border bg-white p-6"
                        key={service.id}
                    >
                        <h2 className="text-lg font-semibold">
                            {service.name}
                        </h2>
                        <p className="mt-1 text-sm text-slate-600">
                            {
                                serviceBehaviors.find(
                                    (b) => b.value === service.behavior_type,
                                )?.label
                            }{" "}
                            ·{" "}
                            {service.archived_at
                                ? "Archived"
                                : service.active
                                  ? "Enabled"
                                  : "Disabled"}
                        </p>
                        <p className="mt-3 text-sm text-slate-600">
                            {service.description}
                        </p>
                        {service.products_count !== undefined && (
                            <p className="mt-3 text-xs font-medium text-slate-500">
                                {service.behavior_type === "direct_sale"
                                    ? (service.listings_count ?? 0)
                                    : service.products_count}{" "}
                                {service.behavior_type === "direct_sale"
                                    ? "items"
                                    : "packages"}
                            </p>
                        )}
                        <Link
                            href={`/admin/add-on-services/${service.id}`}
                            className="mt-4 inline-block text-sm font-semibold text-emerald-800 underline"
                        >
                            Open Service
                        </Link>
                    </article>
                ))}
            </div>
            {!services.data.length && (
                <p className="rounded-xl border border-dashed bg-white p-10 text-center text-slate-500">
                    No add-on services yet. Create a service when you are ready
                    to offer one.
                </p>
            )}
            <PaginationControls {...services} />
        </AdminLayout>
    );
}
