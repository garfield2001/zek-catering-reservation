import type { ReactNode } from "react";
export function EmptyState({
    title,
    description,
    action,
}: {
    title: string;
    description: string;
    action?: ReactNode;
}) {
    return (
        <div className="rounded-xl border border-dashed border-stone-300 bg-white px-6 py-12 text-center">
            <h2 className="text-lg font-semibold">{title}</h2>
            <p className="mt-2 text-sm text-stone-600">{description}</p>
            {action && <div className="mt-5">{action}</div>}
        </div>
    );
}
