import { Minus, Plus } from "lucide-react";
import { useState } from "react";
import { FoodImage } from "../../../components/ui/food-image";
import { Modal } from "../../../components/ui/modal";

export function FoodImagePreview({
    open,
    onClose,
    name,
    category,
    imageUrl,
    thumbnailUrl,
}: {
    open: boolean;
    onClose: () => void;
    name: string;
    category: string | null | undefined;
    imageUrl?: string | null;
    thumbnailUrl?: string | null;
}) {
    const [zoom, setZoom] = useState(1);

    return (
        <Modal open={open} onClose={onClose} title={`Preview ${name}`} wide>
            <p className="text-sm text-slate-600">{category ?? "Food Item"}</p>
            <div className="mt-4 overflow-auto rounded-xl bg-stone-100 p-4">
                {imageUrl || thumbnailUrl ? (
                    <img
                        src={imageUrl ?? thumbnailUrl ?? undefined}
                        alt={name}
                        className="mx-auto max-h-[62dvh] max-w-full origin-center object-contain transition-transform"
                        style={{ transform: `scale(${zoom})` }}
                    />
                ) : (
                    <FoodImage name={name} src={null} fit="contain" />
                )}
            </div>
            <div className="mt-4 flex flex-wrap items-center justify-between gap-3">
                <p className="text-sm font-medium text-slate-800">{name}</p>
                <div
                    className="flex items-center gap-2"
                    aria-label="Image zoom controls"
                >
                    <button
                        type="button"
                        onClick={() =>
                            setZoom((value) => Math.max(1, value - 0.5))
                        }
                        disabled={zoom === 1}
                        className="rounded-lg border px-3 py-2 text-sm font-semibold disabled:opacity-50"
                    >
                        <Minus size={16} aria-hidden="true" />
                        <span className="sr-only">Zoom out</span>
                    </button>
                    <span
                        aria-live="polite"
                        className="min-w-12 text-center text-sm"
                    >
                        {Math.round(zoom * 100)}%
                    </span>
                    <button
                        type="button"
                        onClick={() =>
                            setZoom((value) => Math.min(2, value + 0.5))
                        }
                        disabled={zoom === 2}
                        className="rounded-lg border px-3 py-2 text-sm font-semibold disabled:opacity-50"
                    >
                        <Plus size={16} aria-hidden="true" />
                        <span className="sr-only">Zoom in</span>
                    </button>
                </div>
            </div>
        </Modal>
    );
}
