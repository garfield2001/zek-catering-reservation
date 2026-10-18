import { Link } from "@inertiajs/react";
import { useEffect, useState } from "react";
import { Modal } from "../../../components/ui/modal";
import type { PublishedPackageSnapshot } from "../types";
import { ConfigurationSummary } from "./configuration-summary";
import { PackageEditorButton } from "./package-editor-modal";

type Details = {
    snapshot: PublishedPackageSnapshot;
    status: string;
    published_at: string | null;
    version_number: number;
    active: boolean;
    archived: boolean;
    has_draft: boolean;
    can_edit: boolean;
    availability: { available: boolean; reasons: string[] };
};
export function PackageDetails({
    productId,
    open,
    onClose,
}: {
    productId: number;
    open: boolean;
    onClose: () => void;
}) {
    const [data, setData] = useState<Details | null>(null),
        [error, setError] = useState(""),
        [draft, setDraft] = useState(false),
        [retry, setRetry] = useState(0);
    useEffect(() => {
        if (!open) return;
        const controller = new AbortController();
        fetch(
            `/admin/packages/products/${productId}/details${draft ? "?draft=1" : ""}`,
            {
                headers: { Accept: "application/json" },
                signal: controller.signal,
            },
        )
            .then(async (response) => {
                if (!response.ok)
                    throw new Error(
                        response.status === 403
                            ? "You do not have permission to inspect this configuration. Ask an Administrator for access."
                            : "Details could not be loaded. Reload or try again.",
                    );
                return response.json() as Promise<Details>;
            })
            .then((value) => {
                setData(value);
                setError("");
            })
            .catch((reason: unknown) => {
                if (!controller.signal.aborted)
                    setError(
                        reason instanceof Error
                            ? reason.message
                            : "Details unavailable.",
                    );
            });
        return () => controller.abort();
    }, [open, productId, draft, retry]);
    const close = () => {
        setData(null);
        setDraft(false);
        setError("");
        onClose();
    };
    return (
        <Modal wide open={open} onClose={close} title="Package Details">
            {error ? (
                <p role="alert">
                    {error}{" "}
                    <button
                        type="button"
                        className="underline"
                        onClick={() => {
                            setError("");
                            setRetry(retry + 1);
                        }}
                    >
                        Try again
                    </button>
                </p>
            ) : !data ? (
                <p role="status">Loading saved configuration…</p>
            ) : (
                <div className="space-y-5">
                    <p className="rounded-lg bg-emerald-50 p-3 text-sm">
                        {data.status === "published"
                            ? "Published Version"
                            : "Draft Preview — not published"}{" "}
                        {data.version_number}
                        {data.published_at &&
                            ` · Published ${new Date(data.published_at).toLocaleDateString()}`}
                    </p>
                    <p className="text-sm">
                        {data.archived
                            ? "Archived"
                            : data.active
                              ? "Enabled"
                              : "Disabled"}
                        .{" "}
                        {data.availability.available
                            ? "Current publication is available."
                            : data.availability.reasons.join(" ")}
                    </p>
                    {data.has_draft && (
                        <p className="text-sm">
                            Unpublished Draft changes exist.{" "}
                            {data.can_edit && data.status === "published" && (
                                <button
                                    className="underline"
                                    onClick={() => {
                                        setData(null);
                                        setDraft(true);
                                    }}
                                >
                                    Preview Draft Changes
                                </button>
                            )}
                        </p>
                    )}
                    {draft && (
                        <button
                            className="text-sm underline"
                            onClick={() => {
                                setData(null);
                                setDraft(false);
                            }}
                        >
                            View Published Version
                        </button>
                    )}
                    <ConfigurationSummary snapshot={data.snapshot} />
                    <Link
                        href={`/admin/packages/products/${productId}`}
                        className="mr-4 inline-block text-sm text-slate-600 underline"
                    >
                        Publication & Version History
                    </Link>
                    {data.can_edit && (
                        <PackageEditorButton
                            url={`/admin/packages/products/${productId}/edit`}
                            onClosed={() => setRetry((value) => value + 1)}
                            className="inline-block text-sm text-emerald-800 underline"
                        >
                            Edit Package
                        </PackageEditorButton>
                    )}
                </div>
            )}
        </Modal>
    );
}
