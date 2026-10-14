import { useState } from "react";
import { FoodImage } from "../../../components/ui/food-image";
import { Modal } from "../../../components/ui/modal";

export function FoodImageViewer({
    name,
    imageUrl,
    thumbnailUrl,
}: {
    name: string;
    imageUrl?: string | null;
    thumbnailUrl?: string | null;
}) {
    const [open, setOpen] = useState(false);
    if (!imageUrl) return <FoodImage compact name={name} />;
    return (
        <>
            <button
                type="button"
                aria-label={`View image of ${name}`}
                className="shrink-0 rounded-xl"
                onClick={() => setOpen(true)}
            >
                <FoodImage compact name={name} src={thumbnailUrl ?? imageUrl} />
            </button>
            <Modal open={open} onClose={() => setOpen(false)} title={name}>
                <FoodImage name={name} src={imageUrl} fit="contain" />
            </Modal>
        </>
    );
}
