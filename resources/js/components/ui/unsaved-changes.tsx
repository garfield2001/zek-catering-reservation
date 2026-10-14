import { router } from "@inertiajs/react";
import { useEffect, useRef, useState } from "react";
import { ConfirmDialog } from "./confirm-dialog";

/** Shared native reload protection and accessible Inertia navigation confirmation. */
export function UnsavedChanges({ dirty }: { dirty: boolean }) {
    const [leaving, setLeaving] = useState<string | null>(null);
    const allowLeave = useRef(false);
    useEffect(() => {
        if (!dirty) return;
        const unload = (event: BeforeUnloadEvent) => event.preventDefault();
        window.addEventListener("beforeunload", unload);
        const stop = router.on("before", (event) => {
            if (event.detail.visit.method === "get" && !allowLeave.current) {
                event.preventDefault();
                setLeaving(event.detail.visit.url.toString());
            }
        });
        return () => {
            window.removeEventListener("beforeunload", unload);
            stop();
        };
    }, [dirty]);
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
