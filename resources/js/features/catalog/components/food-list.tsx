import { ArrowDown, ArrowUp, ArrowUpDown, Pencil } from "lucide-react";
import { FoodImageViewer } from "./food-image-viewer";
import type { FoodItem } from "../types";

type SortKey = "name" | "category" | "availability";

/** Dense, server-sorted catalog representation for routine administration. */
export function FoodList({
    items,
    canManage,
    onEdit,
    highlightedId,
    sort,
    direction,
    onSort,
}: {
    items: FoodItem[];
    canManage: boolean;
    onEdit: (item: FoodItem) => void;
    highlightedId?: number | null;
    sort?: SortKey;
    direction?: "asc" | "desc";
    onSort: (column: SortKey) => void;
}) {
    const sortableHeader = (label: string, column: SortKey) => {
        const sorted = sort === column;
        const icon = !sorted ? (
            <ArrowUpDown size={14} aria-hidden="true" />
        ) : direction === "desc" ? (
            <ArrowDown size={14} aria-hidden="true" />
        ) : (
            <ArrowUp size={14} aria-hidden="true" />
        );
        return (
            <button
                type="button"
                className="inline-flex items-center gap-1 rounded text-left hover:text-slate-950"
                onClick={() => onSort(column)}
                aria-label={`Sort by ${label}${sorted ? `, currently ${direction === "desc" ? "descending" : "ascending"}` : ""}`}
            >
                {label}
                {icon}
            </button>
        );
    };

    return (
        <div className="overflow-x-auto rounded-2xl border border-slate-200 bg-white">
            <table className="w-full min-w-[620px] text-left text-sm">
                <thead className="border-b bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th
                            className="px-4 py-3"
                            aria-sort={
                                sort === "name"
                                    ? direction === "desc"
                                        ? "descending"
                                        : "ascending"
                                    : "none"
                            }
                        >
                            {sortableHeader("Food item", "name")}
                        </th>
                        <th
                            className="px-4 py-3"
                            aria-sort={
                                sort === "category"
                                    ? direction === "desc"
                                        ? "descending"
                                        : "ascending"
                                    : "none"
                            }
                        >
                            {sortableHeader("Category", "category")}
                        </th>
                        <th
                            className="px-4 py-3"
                            aria-sort={
                                sort === "availability"
                                    ? direction === "desc"
                                        ? "descending"
                                        : "ascending"
                                    : "none"
                            }
                        >
                            {sortableHeader("Availability", "availability")}
                        </th>
                        {canManage && (
                            <th className="px-4 py-3 text-right">Action</th>
                        )}
                    </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                    {items.map((item) => {
                        const available = item.active && item.category?.active;
                        return (
                            <tr
                                key={item.id}
                                role="article"
                                data-highlighted={
                                    highlightedId === item.id || undefined
                                }
                                className={
                                    highlightedId === item.id
                                        ? "bg-emerald-50"
                                        : undefined
                                }
                            >
                                <td className="px-4 py-3">
                                    <div className="flex items-center gap-3">
                                        <FoodImageViewer
                                            name={item.name}
                                            imageUrl={item.image_url}
                                            thumbnailUrl={item.thumbnail_url}
                                        />
                                        <div className="min-w-0">
                                            <h2 className="truncate font-semibold text-slate-900">
                                                {item.name}
                                            </h2>
                                            {item.description && (
                                                <p className="max-w-md truncate text-xs text-slate-500">
                                                    {item.description}
                                                </p>
                                            )}
                                        </div>
                                    </div>
                                </td>
                                <td className="px-4 py-3 text-slate-600">
                                    {item.category?.name ?? "Uncategorized"}
                                </td>
                                <td className="px-4 py-3">
                                    <span
                                        className={`rounded-full px-2 py-1 text-xs font-medium ${available ? "bg-emerald-50 text-emerald-800" : "bg-amber-50 text-amber-800"}`}
                                    >
                                        {available
                                            ? "Available"
                                            : "Unavailable"}
                                    </span>
                                </td>
                                {canManage && (
                                    <td className="px-4 py-3 text-right">
                                        <button
                                            type="button"
                                            aria-label={`Edit food ${item.name}`}
                                            onClick={() => onEdit(item)}
                                            className="inline-flex items-center gap-1 rounded-lg px-2 py-1.5 text-emerald-800 hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-600"
                                        >
                                            <Pencil size={14} />
                                            Edit
                                        </button>
                                    </td>
                                )}
                            </tr>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );
}
