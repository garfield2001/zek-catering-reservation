import { ArrowRight, Clock3, History } from "lucide-react";

export type AuditEvent = {
    id: number;
    action: string;
    actor_label: string;
    subject_type: string;
    subject_id: number | null;
    created_at: string;
    before_data: Record<string, unknown> | null;
    after_data: Record<string, unknown> | null;
};

const labels: Record<string, string> = {
    "access.permissions_updated": "Permissions updated",
    "catalog.collection_created": "Collection created",
    "catalog.collection_archived": "Collection archived",
    "catalog.family_presentation_updated": "Collection details updated",
    "catalog.family_default_updated": "Ordering rules updated",
    "package.draft_created": "Package draft created",
    "package.draft_updated": "Package draft updated",
    "package.group_created": "Inclusion group added",
    "package.group_updated": "Inclusion group updated",
    "package.group_deleted": "Inclusion group removed",
};

const sensitive = /password|token|secret|cookie|authorization|remember/i;
const titleCase = (value: string) =>
    value
        .replaceAll("_", " ")
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
export const actionLabel = (action: string) =>
    labels[action] ?? titleCase(action.split(".").at(-1) ?? action);
const fieldLabel = (field: string) =>
    titleCase(field.replaceAll(".", " › ").replace(/_minor\b/g, " (centavos)"));

function flatten(value: unknown, prefix = ""): Record<string, unknown> {
    if (!value || typeof value !== "object" || Array.isArray(value))
        return prefix ? { [prefix]: value } : {};
    return Object.entries(value).reduce<Record<string, unknown>>(
        (result, [key, nested]) => {
            if (sensitive.test(key)) return result;
            const path = prefix ? `${prefix}.${key}` : key;
            return {
                ...result,
                ...(nested &&
                typeof nested === "object" &&
                !Array.isArray(nested)
                    ? flatten(nested, path)
                    : { [path]: nested }),
            };
        },
        {},
    );
}

function valueLabel(value: unknown, field: string): string {
    if (value === null || value === undefined) return "—";
    if (typeof value === "boolean")
        return field.endsWith("active")
            ? value
                ? "Enabled"
                : "Disabled"
            : value
              ? "Yes"
              : "No";
    if (field.endsWith("_minor") && /^\d+$/.test(String(value))) {
        const centavos = BigInt(String(value));
        return `₱${(centavos / 100n).toLocaleString("en-PH")}.${(centavos % 100n).toString().padStart(2, "0")}`;
    }
    if (Array.isArray(value))
        return value.length === 0
            ? "None"
            : `${value.length} item${value.length === 1 ? "" : "s"}`;
    if (typeof value === "object") return "Updated details";
    return String(value).replaceAll("_", " ");
}

export function AuditList({ events }: { events: AuditEvent[] }) {
    if (events.length === 0)
        return (
            <div className="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center">
                <History
                    className="mx-auto text-slate-400"
                    size={32}
                    aria-hidden="true"
                />
                <h2 className="mt-3 font-semibold">No activity recorded yet</h2>
                <p className="mt-1 text-sm text-slate-600">
                    Administrative and security changes will appear here.
                </p>
            </div>
        );
    return (
        <div className="space-y-3">
            {events.map((event) => {
                const before = flatten(event.before_data);
                const after = flatten(event.after_data);
                const fields = [
                    ...new Set([...Object.keys(before), ...Object.keys(after)]),
                ]
                    .filter(
                        (field) =>
                            JSON.stringify(before[field]) !==
                            JSON.stringify(after[field]),
                    )
                    .slice(0, 12);
                return (
                    <article
                        key={event.id}
                        className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
                    >
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h2 className="font-semibold text-slate-900">
                                    {actionLabel(event.action)}
                                </h2>
                                <p className="mt-1 text-sm text-slate-600">
                                    {event.actor_label} ·{" "}
                                    {titleCase(event.subject_type)}
                                    {event.subject_id
                                        ? ` #${event.subject_id}`
                                        : ""}
                                </p>
                            </div>
                            <time
                                className="inline-flex items-center gap-1 text-xs text-slate-500"
                                dateTime={event.created_at}
                            >
                                <Clock3 size={14} aria-hidden="true" />
                                {new Date(event.created_at).toLocaleString(
                                    "en-PH",
                                    {
                                        timeZone: "Asia/Manila",
                                        dateStyle: "medium",
                                        timeStyle: "short",
                                    },
                                )}
                            </time>
                        </div>
                        {fields.length > 0 && (
                            <dl className="mt-4 divide-y divide-slate-100 rounded-lg bg-slate-50 px-4">
                                {fields.map((field) => (
                                    <div
                                        key={field}
                                        className="grid gap-1 py-2 text-sm sm:grid-cols-[11rem_1fr]"
                                    >
                                        <dt className="font-medium text-slate-600">
                                            {fieldLabel(field)}
                                        </dt>
                                        <dd className="flex flex-wrap items-center gap-2 break-all text-slate-800">
                                            <span>
                                                {valueLabel(
                                                    before[field],
                                                    field,
                                                )}
                                            </span>
                                            <ArrowRight
                                                size={14}
                                                className="shrink-0 text-slate-400"
                                                aria-hidden="true"
                                            />
                                            <strong>
                                                {valueLabel(
                                                    after[field],
                                                    field,
                                                )}
                                            </strong>
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                        )}
                        {(event.before_data || event.after_data) && (
                            <details className="mt-3 text-sm">
                                <summary className="cursor-pointer font-medium text-emerald-800">
                                    Technical details
                                </summary>
                                <pre className="mt-2 max-h-72 overflow-auto rounded-lg bg-slate-950 p-4 text-xs text-slate-100">
                                    {JSON.stringify(
                                        {
                                            before: event.before_data,
                                            after: event.after_data,
                                        },
                                        (key, value) =>
                                            sensitive.test(key)
                                                ? undefined
                                                : value,
                                        2,
                                    )}
                                </pre>
                            </details>
                        )}
                    </article>
                );
            })}
        </div>
    );
}
