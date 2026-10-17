import { useEffect, useState } from "react";
import { Link, router } from "@inertiajs/react";
import { toast } from "sonner";
import { Modal } from "../../../components/ui/modal";
import { ListingForm } from "./listing-form";
import type { ListingEditorProps } from "../types";

function Editor({
    url,
    onClose,
    onSaved,
}: {
    url: string;
    onClose: () => void;
    onSaved: (id: number) => void;
}) {
    const [data, setData] = useState<ListingEditorProps | null>(null);
    const [error, setError] = useState("");
    useEffect(() => {
        const controller = new AbortController();
        fetch(url, {
            headers: { Accept: "application/json" },
            signal: controller.signal,
        })
            .then((response) => {
                if (!response.ok)
                    throw new Error(
                        "This item cannot be edited. Check your access and its availability, then refresh the workspace.",
                    );
                return response.json();
            })
            .then(setData)
            .catch((reason) => {
                if (!controller.signal.aborted) setError(reason.message);
            });
        return () => controller.abort();
    }, [url]);
    return (
        <Modal open wide title="Item configuration" onClose={onClose}>
            {error ? (
                <p role="alert">{error}</p>
            ) : data ? (
                <ListingForm {...data} onSaved={onSaved} />
            ) : (
                <p role="status">Loading item…</p>
            )}
        </Modal>
    );
}

export function ListingEditorButton({
    url,
    children,
}: {
    url: string;
    children: string;
}) {
    const [open, setOpen] = useState(false);
    const [savedId, setSavedId] = useState<number | null>(null);
    return (
        <>
            <button
                className="text-sm font-semibold text-emerald-800 underline"
                onClick={() => setOpen(true)}
            >
                {children}
            </button>
            {savedId && (
                <Link
                    className="text-sm underline"
                    href={`/admin/items/${savedId}`}
                >
                    View saved item
                </Link>
            )}
            {open && (
                <Editor
                    url={url}
                    onClose={() => setOpen(false)}
                    onSaved={(id) => {
                        setOpen(false);
                        setSavedId(id);
                        toast.success(
                            "Item Draft saved. Published facts remain unchanged.",
                        );
                        setTimeout(() => router.reload(), 0);
                    }}
                />
            )}
        </>
    );
}
