import { useState } from "react";

/** Modal saves return data, leaving navigation/filter state with the workspace. */
export function useJsonSave<T>() {
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState("");
    async function save(
        url: string,
        method: "POST" | "PUT",
        data: object,
        validation: (errors: Record<string, string>) => void,
    ): Promise<T | undefined> {
        setSaving(true);
        setError("");
        try {
            const response = await fetch(url, {
                method,
                credentials: "same-origin",
                headers: {
                    Accept: "application/json",
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN":
                        document.querySelector<HTMLMetaElement>(
                            'meta[name="csrf-token"]',
                        )?.content ?? "",
                },
                body: JSON.stringify(data),
            });
            if (response.status === 422) {
                const result = await response.json();
                validation(
                    Object.fromEntries(
                        Object.entries(result.errors ?? {}).map(
                            ([key, values]) => [
                                key,
                                (values as string[]).join(" "),
                            ],
                        ),
                    ),
                );
                return;
            }
            if (!response.ok)
                throw new Error(
                    "The save could not be completed. Check your access and session before retrying. Your input has been retained.",
                );
            return (await response.json()) as T;
        } catch (reason) {
            setError(
                reason instanceof Error
                    ? reason.message
                    : "Save failed. Your input has been retained.",
            );
        } finally {
            setSaving(false);
        }
    }
    return { save, saving, error };
}
