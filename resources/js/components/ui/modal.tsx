import * as Dialog from "@radix-ui/react-dialog";
import { X } from "lucide-react";
import { useRef, type ReactNode } from "react";

/** The single Radix-backed dialog/sheet foundation for application forms. */
export function Modal({
    open,
    onClose,
    title,
    children,
    sheet = false,
    wide = false,
}: {
    open: boolean;
    onClose: () => void;
    title: string;
    children: ReactNode;
    sheet?: boolean;
    wide?: boolean;
}) {
    const restoreFocus = useRef<HTMLElement | null>(null);
    return (
        <Dialog.Root
            open={open}
            onOpenChange={(value) => {
                if (!value) onClose();
            }}
        >
            <Dialog.Portal>
                <Dialog.Overlay className="fixed inset-0 z-50 bg-slate-950/45 backdrop-blur-[2px]" />
                <Dialog.Content
                    aria-describedby={undefined}
                    onOpenAutoFocus={() => {
                        restoreFocus.current =
                            document.activeElement as HTMLElement;
                    }}
                    onCloseAutoFocus={(e) => {
                        e.preventDefault();
                        restoreFocus.current?.focus();
                    }}
                    onPointerDownOutside={(e) => e.preventDefault()}
                    className={
                        sheet
                            ? "fixed inset-y-0 left-0 z-50 w-[min(86vw,20rem)] overflow-y-auto bg-white p-5 shadow-xl"
                            : `fixed left-1/2 top-1/2 z-50 max-h-[90dvh] ${wide ? "w-[min(94vw,56rem)]" : "w-[min(94vw,40rem)]"} -translate-x-1/2 -translate-y-1/2 overflow-y-auto rounded-2xl border border-slate-200 bg-white shadow-xl`
                    }
                >
                    <div className="sticky top-0 z-10 flex items-center justify-between gap-4 border-b border-slate-100 bg-white px-6 py-4">
                        <Dialog.Title className="text-lg font-semibold tracking-tight">
                            {title}
                        </Dialog.Title>
                        <Dialog.Close
                            className="rounded-lg p-2 text-slate-500 hover:bg-slate-100"
                            aria-label="Close dialog"
                        >
                            <X size={18} />
                        </Dialog.Close>
                    </div>
                    <div className="p-6">{children}</div>
                </Dialog.Content>
            </Dialog.Portal>
        </Dialog.Root>
    );
}
