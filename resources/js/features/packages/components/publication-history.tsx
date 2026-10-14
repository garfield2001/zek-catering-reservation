import { Link } from "@inertiajs/react";
import { ConfigurationSummary } from "./configuration-summary";
import type { PackageVersion } from "../types";
export function PublicationHistory({
    productId,
    history,
    canRevise,
}: {
    productId: number;
    history: PackageVersion[];
    canRevise: boolean;
}) {
    return (
        <div className="space-y-4">
            {!history.length && (
                <p className="text-sm text-slate-500">
                    No publications yet. Your first publication will appear
                    here.
                </p>
            )}
            {[...history].reverse().map((version) => (
                <details key={version.id} className="rounded-xl border p-4">
                    <summary className="cursor-pointer font-medium">
                        Version {version.version_number} ·{" "}
                        {version.status === "published"
                            ? "Published"
                            : "Abandoned"}
                    </summary>
                    <div className="mt-4">
                        {version.published_snapshot && (
                            <ConfigurationSummary
                                snapshot={version.published_snapshot}
                            />
                        )}
                        {version.status === "published" && canRevise && (
                            <Link
                                method="post"
                                as="button"
                                href={`/admin/packages/products/${productId}/versions/${version.id}/revise`}
                                className="mt-4 rounded-lg border border-emerald-700 px-4 py-2 text-sm font-semibold text-emerald-800"
                            >
                                Create New Draft from Version{" "}
                                {version.version_number}
                            </Link>
                        )}
                    </div>
                </details>
            ))}
        </div>
    );
}
