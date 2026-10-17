import { router } from "@inertiajs/react";
import { useEffect, useRef, useState } from "react";

/** One debounced, cancellable visit stream; filtering always resets pagination. */
export function useLiveFilters<T extends Record<string, string>>(
    url: string,
    initial: T,
) {
    const [filters, setFilters] = useState(initial);
    const [pending, setPending] = useState(false);
    const previous = useRef(JSON.stringify(initial));
    const cancel = useRef<(() => void) | null>(null);
    const sequence = useRef(0);
    const serialized = JSON.stringify(filters);
    useEffect(() => {
        cancel.current?.();
        const request = ++sequence.current;
        if (previous.current === serialized) return;
        const timer = window.setTimeout(() => {
            router.get(url, JSON.parse(serialized), {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onCancelToken: (token) => {
                    cancel.current = () => token.cancel();
                },
                onStart: () => setPending(true),
                onSuccess: () => {
                    if (sequence.current === request)
                        previous.current = serialized;
                },
                onFinish: () => {
                    if (sequence.current === request) setPending(false);
                },
            });
        }, 300);
        return () => window.clearTimeout(timer);
    }, [serialized, url]);
    useEffect(() => () => cancel.current?.(), []);
    return { filters, setFilters, pending };
}
