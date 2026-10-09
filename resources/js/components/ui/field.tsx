import type { ReactNode } from "react";
export function Field({
    label,
    error,
    children,
    id,
}: {
    label: string;
    error?: string;
    children: ReactNode;
    id: string;
}) {
    return (
        <div className="space-y-1.5">
            <label className="block text-sm font-medium" htmlFor={id}>
                {label}
            </label>
            {children}
            {error && (
                <p
                    id={id + "-error"}
                    role="alert"
                    className="text-sm text-red-700"
                >
                    {error}
                </p>
            )}
        </div>
    );
}
