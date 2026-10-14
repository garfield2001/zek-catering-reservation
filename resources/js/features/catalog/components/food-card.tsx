import { Link } from "@inertiajs/react";
import { Pencil, Plus } from "lucide-react";
import { FoodImageViewer } from "./food-image-viewer";
import type { FoodItem } from "../types";

export function FoodCard({
    item,
    canManage,
    onEdit,
}: {
    item: FoodItem;
    canManage: boolean;
    onEdit: () => void;
}) {
    return (
        <article className="rounded-xl border bg-white p-4 shadow-sm">
            <div className="flex gap-3">
                <FoodImageViewer
                    name={item.name}
                    imageUrl={item.image_url}
                    thumbnailUrl={item.thumbnail_url}
                />
                <div className="min-w-0 flex-1">
                    <h3 className="break-words font-semibold">{item.name}</h3>
                    <span
                        className={`mt-1 inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${item.active ? "bg-emerald-50 text-emerald-800" : "bg-slate-100 text-slate-600"}`}
                    >
                        {item.active ? "Active" : "Inactive"}
                    </span>
                </div>
                {canManage && (
                    <button
                        onClick={onEdit}
                        aria-label={`Edit food ${item.name}`}
                        className="self-start rounded-lg p-2 text-slate-500 hover:bg-slate-100"
                    >
                        <Pencil size={16} />
                    </button>
                )}
            </div>
            {item.description && (
                <p className="mt-3 line-clamp-2 text-sm text-slate-600">
                    {item.description}
                </p>
            )}
            <p className="mt-3 text-xs leading-5 text-slate-500">
                {item.eligible_families
                    ?.filter((f) => f.pivot?.active)
                    .map((f) => (f.is_addon ? f.name : "Catering Packages"))
                    .join(" · ") || "Not assigned to a service"}
            </p>
            {item.eligible_families?.some(
                (f) => f.is_addon && f.pivot?.active,
            ) && (
                <details className="mt-3 border-t pt-3 text-xs">
                    <summary className="font-medium text-emerald-800">
                        Add-on preparations & serving sizes
                    </summary>
                    <div className="mt-3 space-y-3">
                        {item.offerings
                            ?.filter((o) => o.family?.is_addon)
                            .map((o) => (
                                <Link
                                    key={o.id}
                                    href={`/admin/catalog/dishes/${item.id}/offerings/${o.id}`}
                                    className="block text-emerald-800 underline"
                                >
                                    {o.name} · {o.family?.name} ·{" "}
                                    {o.options?.length ?? 0} sizes
                                </Link>
                            ))}
                        {canManage && (
                            <Link
                                className="inline-flex items-center gap-1 text-emerald-800 underline"
                                href={`/admin/catalog/dishes/${item.id}/offerings/create`}
                            >
                                <Plus size={14} />
                                Add preparation for {item.name}
                            </Link>
                        )}
                    </div>
                </details>
            )}
        </article>
    );
}
