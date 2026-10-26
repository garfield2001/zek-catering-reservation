import { useState } from "react";

export function userInitials(name: string): string {
    return (
        name
            .trim()
            .split(/\s+/)
            .filter(Boolean)
            .slice(0, 2)
            .map((part) => part[0]?.toUpperCase())
            .join("") || "U"
    );
}

export function UserAvatar({
    name,
    src,
    size = "md",
}: {
    name: string;
    src?: string | null;
    size?: "sm" | "md" | "lg";
}) {
    const [failed, setFailed] = useState(false);
    const dimensions = {
        sm: "size-8 text-xs",
        md: "size-10 text-sm",
        lg: "size-24 text-2xl",
    };
    return (
        <span
            className={`relative grid shrink-0 place-items-center overflow-hidden rounded-full bg-emerald-100 font-semibold text-emerald-900 ${dimensions[size]}`}
            aria-label={`${name} profile photo`}
        >
            <span aria-hidden={Boolean(src && !failed)}>
                {userInitials(name)}
            </span>
            {src && !failed && (
                <img
                    src={src}
                    alt=""
                    className="absolute inset-0 size-full object-cover"
                    onError={() => setFailed(true)}
                />
            )}
        </span>
    );
}
