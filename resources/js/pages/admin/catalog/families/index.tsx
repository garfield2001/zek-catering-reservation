import { router } from "@inertiajs/react";
import { Boxes, Plus, Sparkles } from "lucide-react";
import { useState } from "react";
import { Modal } from "../../../../components/ui/modal";
import { PaginationControls } from "../../../../components/ui/pagination";
import { CollectionCreateForm } from "../../../../features/catalog/components/collection-create-form";
import {
    CollectionCard,
    behaviorNames,
} from "../../../../features/catalog/components/collection-card";
import type { Collection } from "../../../../features/catalog/types";
import AdminLayout from "../../../../layouts/admin-layout";
import type { Pagination } from "../../../../types";

export default function CollectionIndex({
    families,
    canManagePresentation,
    canManageDefaults,
    hasPrimary,
    filters,
}: {
    families: Pagination<Collection>;
    canManagePresentation: boolean;
    canManageDefaults: boolean;
    hasPrimary: boolean;
    filters: { q: string; status: string; sort: string };
}) {
    const [creating, setCreating] = useState(false);
    const [archive, setArchive] = useState<Collection | null>(null);
    const current = families.data.filter((family) => !family.archived_at);
    const archived = families.data.filter((family) => family.archived_at);
    return (
        <AdminLayout
            title="Collections"
            description="Organize primary catering and optional add-ons. Names can change; configuration types and catalog links stay stable."
        >
            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <p className="text-sm text-slate-600">
                    {families.total} matching collection
                    {families.total === 1 ? "" : "s"}
                </p>
                {canManagePresentation && (
                    <button
                        type="button"
                        onClick={() => setCreating(true)}
                        className="inline-flex items-center gap-2 rounded-lg bg-emerald-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-900"
                    >
                        <Plus size={17} aria-hidden="true" /> Create collection
                    </button>
                )}
            </div>
            <form
                method="get"
                action="/admin/catalog/families"
                className="mb-6 grid gap-3 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-[minmax(0,1fr)_auto_auto_auto]"
            >
                <label className="sr-only" htmlFor="collection-search">
                    Search collections
                </label>
                <input
                    id="collection-search"
                    name="q"
                    defaultValue={filters.q}
                    placeholder="Search collections"
                    className="rounded-lg border border-slate-300 px-3 py-2 text-sm"
                />
                <label className="sr-only" htmlFor="collection-status">
                    Collection status
                </label>
                <select
                    id="collection-status"
                    name="status"
                    defaultValue={filters.status}
                    aria-label="Collection status"
                    className="rounded-lg border border-slate-300 px-3 py-2 text-sm"
                >
                    <option value="current">Current</option>
                    <option value="archived">Archived</option>
                    <option value="all">All</option>
                </select>
                <label className="sr-only" htmlFor="collection-sort">
                    Sort collections
                </label>
                <select
                    id="collection-sort"
                    name="sort"
                    defaultValue={filters.sort}
                    aria-label="Sort collections"
                    className="rounded-lg border border-slate-300 px-3 py-2 text-sm"
                >
                    <option value="display">Display order</option>
                    <option value="name">Name</option>
                </select>
                <button
                    type="submit"
                    className="rounded-lg border border-emerald-700 px-4 py-2 text-sm font-semibold text-emerald-900 hover:bg-emerald-50"
                >
                    Apply
                </button>
            </form>
            {!hasPrimary && (
                <section className="mb-6 flex gap-4 rounded-2xl border border-emerald-200 bg-emerald-50 p-5">
                    <Sparkles
                        className="shrink-0 text-emerald-700"
                        size={24}
                        aria-hidden="true"
                    />
                    <div>
                        <h2 className="font-semibold text-emerald-950">
                            Set up primary catering
                        </h2>
                        <p className="mt-1 text-sm text-emerald-900">
                            Create your primary collection, then add food items,
                            offerings and package variants. A fresh catalog is
                            expected to be empty.
                        </p>
                        {canManagePresentation && (
                            <button
                                type="button"
                                onClick={() => setCreating(true)}
                                className="mt-3 text-sm font-semibold text-emerald-900 underline"
                            >
                                Start setup
                            </button>
                        )}
                    </div>
                </section>
            )}
            {current.length === 0 && archived.length === 0 ? (
                <div className="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center">
                    <Boxes
                        className="mx-auto text-slate-400"
                        size={34}
                        aria-hidden="true"
                    />
                    <h2 className="mt-3 font-semibold">
                        {filters.q || filters.status !== "current"
                            ? "No matching collections"
                            : "No collections yet"}
                    </h2>
                    <p className="mt-2 text-sm text-slate-600">
                        Your business catalog starts here. Nothing has been
                        pre-created for you.
                    </p>
                </div>
            ) : (
                <div className="space-y-4">
                    {current.map((family) => (
                        <CollectionCard
                            key={family.id}
                            family={family}
                            canManage={canManagePresentation}
                            canManageDefaults={canManageDefaults}
                            onArchive={() => setArchive(family)}
                        />
                    ))}
                </div>
            )}
            {archived.length > 0 && (
                <details className="mt-8">
                    <summary className="cursor-pointer text-sm font-semibold text-slate-600">
                        Archived collections ({archived.length})
                    </summary>
                    <ul className="mt-3 space-y-2">
                        {archived.map((family) => (
                            <li
                                key={family.id}
                                className="rounded-lg border border-slate-200 bg-white p-3 text-sm"
                            >
                                {family.name} ·{" "}
                                {behaviorNames[family.behavior_type]} · history
                                retained
                            </li>
                        ))}
                    </ul>
                </details>
            )}
            <PaginationControls {...families} />
            <Modal
                open={creating}
                onClose={() => setCreating(false)}
                title="Create collection"
            >
                <CollectionCreateForm
                    hasPrimary={hasPrimary}
                    onCancel={() => setCreating(false)}
                    onCreated={() => setCreating(false)}
                />
            </Modal>
            <Modal
                open={archive !== null}
                onClose={() => setArchive(null)}
                title="Archive collection?"
            >
                <p className="text-sm text-slate-700">
                    Archive <strong>{archive?.name}</strong>? It will no longer
                    be available for new configuration or selection. Existing
                    food, packages and activity history remain linked.
                </p>
                <div className="mt-6 flex justify-end gap-3">
                    <button
                        type="button"
                        onClick={() => setArchive(null)}
                        className="rounded-lg px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        onClick={() => {
                            if (archive)
                                router.post(
                                    `/admin/catalog/families/${archive.id}/archive`,
                                    {},
                                    { onSuccess: () => setArchive(null) },
                                );
                        }}
                        className="rounded-lg bg-rose-700 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-800"
                    >
                        Archive collection
                    </button>
                </div>
            </Modal>
        </AdminLayout>
    );
}
