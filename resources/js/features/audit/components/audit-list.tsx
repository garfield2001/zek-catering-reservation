export type AuditEvent = {
    id: number;
    action: string;
    actor_label: string;
    subject_id: number | null;
    created_at: string;
    before_data: Record<string, unknown> | null;
    after_data: Record<string, unknown> | null;
};
export function AuditList({ events }: { events: AuditEvent[] }) {
    return (
        <div className="divide-y divide-stone-200 rounded-xl border border-stone-200 bg-white">
            {events.length === 0 && (
                <p className="p-6 text-stone-500">No audit events yet.</p>
            )}
            {events.map((event) => (
                <article key={event.id} className="p-5">
                    <div className="flex flex-wrap justify-between gap-2">
                        <h2 className="font-medium">{event.action}</h2>
                        <time
                            className="text-sm text-stone-500"
                            dateTime={event.created_at}
                        >
                            {new Date(event.created_at).toLocaleString(
                                "en-PH",
                                { timeZone: "Asia/Manila" },
                            )}
                        </time>
                    </div>
                    <p className="mt-1 text-sm text-stone-600">
                        {event.actor_label} · Account #{event.subject_id ?? "—"}
                    </p>
                    {(event.before_data || event.after_data) && (
                        <details className="mt-3 text-sm">
                            <summary className="cursor-pointer text-emerald-800">
                                View recorded changes
                            </summary>
                            <pre className="mt-2 overflow-auto rounded bg-stone-100 p-3 text-xs">
                                {JSON.stringify(
                                    {
                                        before: event.before_data,
                                        after: event.after_data,
                                    },
                                    null,
                                    2,
                                )}
                            </pre>
                        </details>
                    )}
                </article>
            ))}
        </div>
    );
}
