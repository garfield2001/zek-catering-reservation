import { useLiveFilters } from "../../catalog/use-live-filters";

export function PackageFilters({
    url,
    initial,
}: {
    url: string;
    initial: { q?: string; status?: string };
}) {
    const { filters, setFilters, pending } = useLiveFilters(url, {
        q: initial.q ?? "",
        status: initial.status ?? "all",
    });
    return (
        <div className="my-5 flex flex-wrap items-end gap-3">
            <label className="min-w-48 flex-1 text-sm">
                Search packages
                <input
                    value={filters.q}
                    placeholder="Package name"
                    onChange={(event) =>
                        setFilters((current) => ({
                            ...current,
                            q: event.target.value,
                        }))
                    }
                />
            </label>
            <label className="text-sm">
                Package status
                <select
                    value={filters.status}
                    onChange={(event) =>
                        setFilters((current) => ({
                            ...current,
                            status: event.target.value,
                        }))
                    }
                >
                    <option value="all">All statuses</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="archived">Archived</option>
                </select>
            </label>
            {(filters.q || filters.status !== "all") && (
                <button
                    type="button"
                    className="px-3 py-2 text-sm underline"
                    onClick={() => setFilters({ q: "", status: "all" })}
                >
                    Clear filters
                </button>
            )}
            <span role="status" className="text-sm text-slate-500">
                {pending ? "Updating packages…" : ""}
            </span>
        </div>
    );
}
