import { useEffect, useState, useRef } from "react";
import { Field } from "../../../components/ui/field";
import { FoodImage } from "../../../components/ui/food-image";

export function ImageUploadField({
    id,
    name,
    currentUrl,
    removing,
    onChange,
    error,
}: {
    id: string;
    name: string;
    currentUrl?: string | null;
    removing: boolean;
    onChange: (file: File | null, remove: boolean) => void;
    error?: string;
}) {
    const input = useRef<HTMLInputElement>(null);
    const [preview, setPreview] = useState<string | null>(null);
    useEffect(
        () => () => {
            if (preview) URL.revokeObjectURL(preview);
        },
        [preview],
    );
    return (
        <div className="space-y-3 rounded-xl border bg-slate-50 p-4">
            <Field id={id} label="Food image (optional)" error={error}>
                <input
                    ref={input}
                    id={id}
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    onChange={(event) => {
                        const file = event.target.files?.[0] ?? null;
                        setPreview(
                            file &&
                                [
                                    "image/jpeg",
                                    "image/png",
                                    "image/webp",
                                ].includes(file.type)
                                ? URL.createObjectURL(file)
                                : null,
                        );
                        onChange(file, false);
                    }}
                />
            </Field>
            <p className="text-xs text-slate-500">
                JPEG, PNG or WebP, up to 5 MB and 3000 × 3000 pixels. Images are
                optimized when saved.
            </p>
            <div className="flex items-center gap-4">
                <FoodImage
                    compact
                    name={name || "Food"}
                    src={removing ? null : (preview ?? currentUrl)}
                />
                {preview && (
                    <span className="text-xs text-slate-600">
                        Selected image preview — not saved yet
                    </span>
                )}
                {currentUrl && (
                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={removing}
                            onChange={(event) => {
                                setPreview(null);
                                if (input.current) input.current.value = "";
                                onChange(null, event.target.checked);
                            }}
                        />
                        Remove saved image
                    </label>
                )}
            </div>
        </div>
    );
}
