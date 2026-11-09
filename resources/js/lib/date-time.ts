export function formatManilaEvent(
    date: string | null,
    time?: string | null,
): string {
    if (!date) return "Event date not set";

    const [year, month, day] = date.slice(0, 10).split("-").map(Number);
    const label = new Intl.DateTimeFormat("en-PH", {
        timeZone: "UTC",
        month: "long",
        day: "numeric",
        year: "numeric",
    }).format(new Date(Date.UTC(year, month - 1, day)));

    return time ? `${label} · ${formatManilaTime(time)}` : label;
}

export function formatManilaTime(time: string | null | undefined): string {
    if (!time) return "Time to be confirmed";

    const [hour, minute] = time.slice(0, 5).split(":").map(Number);
    const suffix = hour >= 12 ? "PM" : "AM";
    const displayHour = hour % 12 || 12;

    return `${displayHour}:${String(minute).padStart(2, "0")} ${suffix}`;
}

export function formatManilaTimestamp(value: string | null): string {
    if (!value) return "Not recorded";

    return new Intl.DateTimeFormat("en-PH", {
        timeZone: "Asia/Manila",
        dateStyle: "medium",
        timeStyle: "short",
    }).format(new Date(value));
}
