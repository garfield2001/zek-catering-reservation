/** Map Laravel's dotted field errors without assuming an unchecked JSON shape. */
export function validationErrors(payload: unknown): Record<string, string> {
    if (!payload || typeof payload !== "object" || !("errors" in payload))
        return {};
    const errors = payload.errors;
    if (!errors || typeof errors !== "object" || Array.isArray(errors))
        return {};
    return Object.fromEntries(
        Object.entries(errors).flatMap(([field, messages]) => {
            if (typeof messages === "string") return [[field, messages]];
            if (
                Array.isArray(messages) &&
                messages.every((message) => typeof message === "string")
            )
                return [[field, messages.join(" ")]];
            return [];
        }),
    );
}
