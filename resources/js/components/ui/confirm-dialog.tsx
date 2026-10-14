import * as AlertDialog from "@radix-ui/react-alert-dialog";
import { Button } from "./button";

export function ConfirmDialog({
    open,
    onClose,
    title,
    description,
    onConfirm,
    confirmLabel = "Confirm",
    destructive = false,
    busy = false,
}: {
    open: boolean;
    onClose: () => void;
    title: string;
    description: string;
    onConfirm: () => void;
    confirmLabel?: string;
    destructive?: boolean;
    busy?: boolean;
}) {
    return (
        <AlertDialog.Root
            open={open}
            onOpenChange={(value) => {
                if (!value && !busy) onClose();
            }}
        >
            <AlertDialog.Portal>
                <AlertDialog.Overlay className="fixed inset-0 z-[60] bg-slate-950/45" />
                <AlertDialog.Content className="fixed left-1/2 top-1/2 z-[60] w-[min(94vw,28rem)] -translate-x-1/2 -translate-y-1/2 rounded-2xl border border-slate-200 bg-white p-6 shadow-xl">
                    <AlertDialog.Title className="text-lg font-semibold">
                        {title}
                    </AlertDialog.Title>
                    <AlertDialog.Description className="mt-3 text-sm leading-6 text-slate-600">
                        {description}
                    </AlertDialog.Description>
                    <div className="mt-6 flex flex-wrap justify-end gap-3">
                        <AlertDialog.Cancel asChild>
                            <Button variant="secondary" disabled={busy}>
                                Cancel
                            </Button>
                        </AlertDialog.Cancel>
                        <Button
                            variant={destructive ? "danger" : "primary"}
                            disabled={busy}
                            onClick={onConfirm}
                        >
                            {busy ? "Please wait…" : confirmLabel}
                        </Button>
                    </div>
                </AlertDialog.Content>
            </AlertDialog.Portal>
        </AlertDialog.Root>
    );
}
