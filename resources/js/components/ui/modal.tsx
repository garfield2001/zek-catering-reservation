import * as Dialog from "@radix-ui/react-dialog";
import { X } from "lucide-react";
import { useRef, useState, type ReactNode } from "react";
import { DialogFormContext, type DialogFormState } from "./dialog-form-guard";
import { ConfirmDialog } from "./confirm-dialog";

export const ModalClose = Dialog.Close;

/** The single Radix-backed dialog/sheet foundation for application forms. */
export function Modal({
    open,
    onClose,
    title,
    children,
    sheet = false,
    wide = false,
    dirty = false,
    busy = false,
}: {
    open: boolean;
    onClose: () => void;
    title: string;
    children: ReactNode;
    sheet?: boolean;
    wide?: boolean;
    dirty?: boolean;
    busy?: boolean;
}) {
    const restoreFocus = useRef<HTMLElement | null>(null);
    const [formState, setFormState] = useState<DialogFormState>({
        dirty: false,
        busy: false,
    });
    const [confirm, setConfirm] = useState(false);
    const requestClose = () => {
        if (busy || formState.busy) return;
        if (dirty || formState.dirty) setConfirm(true);
        else onClose();
    };
    return (
        <>
            <Dialog.Root
                open={open}
                onOpenChange={(value) => {
                    if (!value) requestClose();
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
                        <DialogFormContext.Provider value={setFormState}>
                            <div className="p-6">{children}</div>
                        </DialogFormContext.Provider>
                    </Dialog.Content>
                </Dialog.Portal>
            </Dialog.Root>
            <ConfirmDialog
                open={confirm && open}
                onClose={() => setConfirm(false)}
                title="Discard unsaved changes?"
                description="Your saved information will remain unchanged. Pending edits in this form will be lost."
                confirmLabel="Discard Changes"
                cancelLabel="Keep Editing"
                destructive
                onConfirm={() => {
                    setConfirm(false);
                    setFormState({ dirty: false, busy: false });
                    onClose();
                }}
            />
        </>
    );
}
