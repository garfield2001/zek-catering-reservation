import { router } from "@inertiajs/react";
import { useEffect } from "react";
import { Toaster, toast } from "sonner";

export function Notifications() {
    useEffect(() => {
        let pending: ReturnType<typeof setTimeout> | undefined;
        const clear = () => {
            clearTimeout(pending);
            toast.dismiss("request-loading");
        };
        const off = [
            router.on("start", (event) => {
                clear();
                if (event.detail.visit.method !== "get")
                    pending = setTimeout(
                        () =>
                            toast.loading("Saving your changes…", {
                                id: "request-loading",
                            }),
                        1200,
                    );
            }),
            router.on("finish", clear),
            router.on("error", () => {
                clear();
                toast.warning("Please check the highlighted fields.", {
                    id: "validation",
                });
            }),
            router.on("exception", (event) => {
                event.preventDefault();
                clear();
                toast.error(
                    "The request could not be completed. Check your connection and try again.",
                    { id: "request-error" },
                );
            }),
            router.on("invalid", (event) => {
                event.preventDefault();
                clear();
                toast.error(
                    event.detail.response.status === 403
                        ? "Your account cannot perform this action. Ask an Administrator to review your access."
                        : event.detail.response.status === 419 ||
                            event.detail.response.status === 401
                          ? "Your session has expired. Refresh the page and sign in again."
                          : "The server could not complete this request. Refresh the page and try again.",
                    { id: "request-error" },
                );
            }),
        ];
        return () => {
            clear();
            off.forEach((unsubscribe) => unsubscribe());
        };
    }, []);
    return (
        <Toaster
            position="bottom-right"
            richColors
            closeButton
            duration={5000}
            toastOptions={{ className: "font-sans" }}
        />
    );
}

const shown = new Set<string>();
export function useFlashToast(flash: { status?: string; id?: string }) {
    useEffect(() => {
        if (!flash.status || !flash.id || shown.has(flash.id)) return;
        shown.add(flash.id);
        if (shown.size > 100) shown.delete(shown.values().next().value!);
        toast.success(flash.status, { id: flash.id });
    }, [flash.id, flash.status]);
}
