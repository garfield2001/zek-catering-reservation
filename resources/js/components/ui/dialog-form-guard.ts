import { createContext, useContext, useEffect } from "react";

export type DialogFormState = { dirty: boolean; busy: boolean };
export const DialogFormContext = createContext<
    ((state: DialogFormState) => void) | null
>(null);

/** Forms register with their nearest dialog; standalone forms need no dialog coupling. */
export function useDialogForm(dirty: boolean, busy: boolean) {
    const register = useContext(DialogFormContext);
    useEffect(() => {
        register?.({ dirty, busy });
        return () => register?.({ dirty: false, busy: false });
    }, [register, dirty, busy]);
}
