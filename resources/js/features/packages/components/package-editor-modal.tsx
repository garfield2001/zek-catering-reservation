import { useEffect, useState, type ReactNode } from "react";
import { Link, router } from "@inertiajs/react";
import { Modal } from "../../../components/ui/modal";
import {
    CompletePackageForm,
    type PackageEditorProps,
} from "./complete-package-form";

/** Fetch the same authorized editor data without navigating away from the workspace. */
export function PackageEditorModal({
    url,
    onClose,
    initialStep = 0,
    onSaved,
}: {
    url: string;
    onClose: () => void;
    initialStep?: number;
    onSaved?: (id: number) => void;
}) {
    const [data, setData] = useState<PackageEditorProps | null>(null);
    const [error, setError] = useState("");
    useEffect(() => {
        const controller = new AbortController();
        fetch(url, {
            headers: { Accept: "application/json" },
            credentials: "same-origin",
            signal: controller.signal,
        })
            .then(async (response) => {
                if (!response.ok)
                    throw new Error(
                        response.status === 403
                            ? "You cannot edit this package. Ask an Administrator to review your access."
                            : "This package cannot be opened. Close this dialog and refresh the workspace.",
                    );
                return response.json();
            })
            .then(setData)
            .catch((reason: Error) => {
                if (!controller.signal.aborted) setError(reason.message);
            });
        return () => controller.abort();
    }, [url]);
    return (
        <Modal
            open
            wide
            onClose={onClose}
            title={
                data?.product ? `Edit ${data.product.name}` : "Create Package"
            }
        >
            {error ? (
                <p role="alert">{error}</p>
            ) : data ? (
                <CompletePackageForm
                    {...data}
                    initialStep={initialStep}
                    onSaved={(id) => {
                        onSaved?.(id);
                        onClose();
                        // Unmount the dirty editor before the preserved-state GET refresh.
                        setTimeout(() => router.reload(), 0);
                    }}
                />
            ) : (
                <p role="status">Loading package configuration…</p>
            )}
        </Modal>
    );
}

export function PackageEditorButton({
    url,
    children,
    className,
    initialStep = 0,
    onClosed,
}: {
    url: string;
    children: ReactNode;
    className?: string;
    initialStep?: number;
    onClosed?: () => void;
}) {
    const [open, setOpen] = useState(false);
    const [savedId, setSavedId] = useState<number | null>(null);
    return (
        <>
            <button
                type="button"
                className={className}
                onClick={() => setOpen(true)}
            >
                {children}
            </button>
            {savedId && (
                <Link
                    href={`/admin/packages/products/${savedId}`}
                    className="ml-3 text-sm underline"
                >
                    View saved package
                </Link>
            )}
            {open && (
                <PackageEditorModal
                    url={url}
                    initialStep={initialStep}
                    onSaved={setSavedId}
                    onClose={() => {
                        setOpen(false);
                        onClosed?.();
                    }}
                />
            )}
        </>
    );
}
