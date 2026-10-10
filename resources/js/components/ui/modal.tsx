import { X } from "lucide-react";
import { useEffect, useRef, type ReactNode } from "react";

export function Modal({
    open,
    onClose,
    title,
    children,
}: {
    open: boolean;
    onClose: () => void;
    title: string;
    children: ReactNode;
}) {
    const dialog = useRef<HTMLDialogElement>(null);
    const previousFocus = useRef<HTMLElement | null>(null);

    useEffect(() => {
        const element = dialog.current;
        if (!element) return;
        if (open && !element.open) {
            previousFocus.current = document.activeElement as HTMLElement;
            element.showModal();
        } else if (!open && element.open) {
            element.close();
            previousFocus.current?.focus();
        }
    }, [open]);

    return (
        <dialog
            ref={dialog}
            aria-label={title}
            onClose={onClose}
            className="m-auto w-[min(92vw,36rem)] max-h-[90vh] overflow-y-auto rounded-2xl border border-slate-200 bg-white p-0 text-slate-900 shadow-2xl backdrop:bg-slate-950/55"
        >
            <div className="sticky top-0 z-10 flex items-center justify-between border-b border-slate-200 bg-white px-6 py-4">
                <h2 className="text-lg font-semibold">{title}</h2>
                <button
                    type="button"
                    aria-label="Close dialog"
                    onClick={onClose}
                    className="rounded-lg p-2 text-slate-500 hover:bg-slate-100"
                >
                    <X size={18} aria-hidden="true" />
                </button>
            </div>
            <div className="p-6">{children}</div>
        </dialog>
    );
}
