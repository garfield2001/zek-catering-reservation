import { useState } from "react";

export function FoodImage({
    src,
    name,
    compact = false,
    portrait = false,
    fit = "cover",
}: {
    src?: string | null;
    name: string;
    compact?: boolean;
    portrait?: boolean;
    fit?: "cover" | "contain";
}) {
    const [failedSource, setFailedSource] = useState<string | null>(null);
    const [loadedSource, setLoadedSource] = useState<string | null>(null);
    const failed = Boolean(src && failedSource === src);
    const loaded = Boolean(src && loadedSource === src);
    return (
        <div
            className={
                compact
                    ? "relative grid size-14 shrink-0 place-items-center overflow-hidden rounded-xl bg-stone-100 text-stone-400"
                    : `relative grid ${portrait ? "aspect-[4/5]" : "aspect-[4/3]"} place-items-center overflow-hidden rounded-xl bg-stone-100 text-stone-400`
            }
        >
            <span aria-hidden="true" className="font-semibold tracking-wide">
                {name
                    .trim()
                    .split(/\s+/)
                    .slice(0, 2)
                    .map((part) => part[0]?.toUpperCase())
                    .join("") || "Food"}
            </span>
            {src && !failed && (
                <img
                    src={src}
                    alt={name}
                    loading="lazy"
                    onLoad={() => setLoadedSource(src)}
                    onError={() => setFailedSource(src)}
                    className={
                        "absolute inset-0 size-full " +
                        (fit === "contain"
                            ? "object-contain "
                            : "object-cover ") +
                        (loaded ? "" : "opacity-0")
                    }
                />
            )}
            {src && !failed && !loaded && (
                <span
                    className="absolute inset-0 animate-pulse bg-stone-200 motion-reduce:animate-none"
                    aria-hidden="true"
                />
            )}
            {(!src || failed) && (
                <span className="sr-only">No image for {name}</span>
            )}
        </div>
    );
}
