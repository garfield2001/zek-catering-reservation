import { router } from "@inertiajs/react";
import { useEffect, useEffectEvent, useRef, useState } from "react";
import { ConfirmDialog } from "./confirm-dialog";

/** Shared native reload protection and accessible Inertia navigation confirmation. */
export function UnsavedChanges({ dirty }: { dirty: boolean }) {
    const [leaving, setLeaving] = useState<string | null>(null);
    const allowLeave = useRef(false);
    const hasUnsavedChanges = useEffectEvent(() => dirty);
    useEffect(() => {
        const unload = (event: BeforeUnloadEvent) => {
            if (hasUnsavedChanges()) event.preventDefault();
        };
        const interceptLink = (event: MouseEvent) => {
            if (
                allowLeave.current ||
                !hasUnsavedChanges() ||
                event.defaultPrevented ||
                event.button !== 0 ||
                event.metaKey ||
                event.ctrlKey ||
                event.shiftKey ||
                event.altKey ||
                !(event.target instanceof Element)
            )
                return;

            const link = event.target.closest<HTMLAnchorElement>("a[href]");
            if (
                !link ||
                link.target === "_blank" ||
                link.hasAttribute("download")
            )
                return;

            const destination = new URL(link.href, window.location.href);
            const current = new URL(window.location.href);
            if (
                destination.origin !== current.origin ||
                (destination.pathname === current.pathname &&
                    destination.search === current.search)
            )
                return;

            // Capture native and Inertia links before their respective
            // navigation handlers run. router.on('before') remains the
            // fallback for programmatic GET visits.
            event.preventDefault();
            event.stopPropagation();
            setLeaving(destination.toString());
        };
        window.addEventListener("beforeunload", unload);
        document.addEventListener("click", interceptLink, true);
        const stop = router.on("before", (event) => {
            if (
                hasUnsavedChanges() &&
                event.detail.visit.method === "get" &&
                !allowLeave.current
            ) {
                event.preventDefault();
                setLeaving(event.detail.visit.url.toString());
            }
        });
        return () => {
            window.removeEventListener("beforeunload", unload);
            document.removeEventListener("click", interceptLink, true);
            stop();
        };
    }, []);
    return (
        <ConfirmDialog
            open={leaving !== null}
            onClose={() => setLeaving(null)}
            title="Discard unsaved changes?"
            description="Saved information remains unchanged. Your unsaved edits will be lost."
            confirmLabel="Discard and leave"
            destructive
            onConfirm={() => {
                allowLeave.current = true;
                if (leaving)
                    router.visit(leaving, {
                        onFinish: () => {
                            allowLeave.current = false;
                            setLeaving(null);
                        },
                    });
            }}
        />
    );
}
