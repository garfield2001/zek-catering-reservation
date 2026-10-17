import { useState } from "react";
import { Pencil, Image as ImageIcon } from "lucide-react";
import { FoodImage } from "../../../components/ui/food-image";
import { Modal } from "../../../components/ui/modal";
import { FoodUsage, type FoodUsageRow } from "./food-usage";
import type { FoodItem } from "../types";

export function FoodCard({
    item,
    canManage,
    onEdit,
    usage = [],
    highlighted = false,
}: {
    item: FoodItem;
    canManage: boolean;
    onEdit: () => void;
    usage?: FoodUsageRow[];
    highlighted?: boolean;
}) {
    const [view, setView] = useState<"image" | "usage" | null>(null);
    return (
        <article
            data-highlighted={highlighted || undefined}
            className={`min-w-0 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm ${highlighted ? "ring-2 ring-emerald-500" : ""}`}
        >
            <div className="relative">
                <button
                    type="button"
                    className="block w-full text-left"
                    aria-label={`View image of ${item.name}`}
                    onClick={() => setView("image")}
                >
                    <FoodImage
                        name={item.name}
                        src={item.thumbnail_url}
                        portrait
                    />
                </button>
                <div className="pointer-events-none absolute inset-x-0 bottom-0 bg-gradient-to-t from-slate-950 via-slate-950/70 to-transparent px-4 pb-4 pt-14 text-white">
                    <h3 className="break-words text-base font-semibold">
                        {item.name}
                    </h3>
                    <p className="mt-1 text-xs text-slate-100">
                        {item.category?.name} ·{" "}
                        {item.active && item.category?.active
                            ? "Active"
                            : "Unavailable"}
                    </p>
                </div>
                {canManage && (
                    <button
                        type="button"
                        onClick={onEdit}
                        aria-label={`Edit food ${item.name}`}
                        className="absolute right-3 top-3 rounded-full bg-white/95 p-2 text-slate-800 shadow"
                    >
                        <Pencil size={16} />
                    </button>
                )}
            </div>
            <div className="flex items-center justify-between gap-2 px-4 py-3 text-xs">
                <button
                    type="button"
                    className="text-emerald-800 underline"
                    onClick={() => setView("usage")}
                >
                    Used In · {usage.length}
                </button>
                <button
                    type="button"
                    className="inline-flex items-center gap-1 text-slate-600"
                    onClick={() => setView("image")}
                >
                    <ImageIcon size={14} />
                    View Image
                </button>
            </div>
            <Modal
                open={view !== null}
                onClose={() => setView(null)}
                title={view === "usage" ? `Used In — ${item.name}` : item.name}
            >
                {view === "usage" ? (
                    <FoodUsage rows={usage} />
                ) : (
                    <FoodImage
                        name={item.name}
                        src={item.image_url}
                        fit="contain"
                    />
                )}
            </Modal>
        </article>
    );
}
