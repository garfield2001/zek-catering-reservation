import { Link } from "@inertiajs/react";

export type FoodUsageRow = {
    product_id: number;
    version_id: number;
    version_number: number;
    name: string;
    service_name: string;
    status: string;
    is_fixed: boolean;
    current: boolean;
    kind: string;
    url: string;
};

export function FoodUsage({ rows }: { rows: FoodUsageRow[] }) {
    return (
        <div className="space-y-3 text-sm">
            <p className="text-slate-600">
                Actual saved commercial relationships visible with your
                permissions. Availability alone does not mean a food is sold.
            </p>
            {rows.length ? (
                <ul className="space-y-3">
                    {rows.map((row) => (
                        <li
                            key={`${row.kind}-${row.version_id}`}
                            className="rounded-lg border p-3"
                        >
                            <Link
                                className="font-medium text-emerald-800 underline"
                                href={row.url}
                            >
                                {row.name}
                            </Link>
                            <p>
                                {row.service_name} ·{" "}
                                {row.status === "draft"
                                    ? "Draft"
                                    : row.current
                                      ? "Current Published"
                                      : "Published History"}{" "}
                                {row.version_number}
                            </p>
                            <p className="text-slate-500">
                                {row.kind === "individual"
                                    ? "Sold individually"
                                    : row.is_fixed
                                      ? "Fixed inclusion"
                                      : "Customer-selectable food"}
                            </p>
                        </li>
                    ))}
                </ul>
            ) : (
                <p>No package or individual product usage is visible yet.</p>
            )}
        </div>
    );
}
