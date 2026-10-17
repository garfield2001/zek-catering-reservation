export type CommercialHistoryData = {
    publications: {
        version_id: number;
        version_number: number;
        published_at: string;
        publisher: string;
        compared_to: number | null;
        changes: { label: string; before: string; after: string }[];
    }[];
    events: {
        id: number;
        actor_label: string;
        action: string;
        created_at: string;
    }[];
    older_events_omitted: boolean;
};

export function CommercialHistory({ data }: { data: CommercialHistoryData }) {
    return (
        <section aria-label="Commercial changes" className="my-5 space-y-4">
            <h3 className="font-semibold">
                Who changed this item and what changed
            </h3>
            {[...data.publications].reverse().map((version) => (
                <details
                    key={version.version_id}
                    className="rounded-lg border p-3"
                >
                    <summary className="cursor-pointer">
                        Version {version.version_number} — published by{" "}
                        {version.publisher} ·{" "}
                        <time dateTime={version.published_at}>
                            {new Date(version.published_at).toLocaleString()}
                        </time>
                    </summary>
                    <p className="my-3 text-sm">
                        {version.compared_to
                            ? `Compared with published Version ${version.compared_to}.`
                            : "First publication: initial commercial configuration."}
                    </p>
                    {!version.changes.length && (
                        <p>No commercial configuration changes.</p>
                    )}
                    <dl className="space-y-3">
                        {version.changes.map((change) => (
                            <div
                                key={change.label}
                                className="rounded border p-3 text-sm break-words"
                            >
                                <dt className="font-semibold">
                                    {change.label}
                                </dt>
                                <dd className="mt-2 grid gap-2 sm:grid-cols-2">
                                    <div>
                                        <span className="font-medium">
                                            Before:{" "}
                                        </span>
                                        {change.before}
                                    </div>
                                    <div>
                                        <span className="font-medium">
                                            After:{" "}
                                        </span>
                                        {change.after}
                                    </div>
                                </dd>
                            </div>
                        ))}
                    </dl>
                </details>
            ))}
            <details className="rounded-lg border p-3">
                <summary className="cursor-pointer">
                    Editing and publication activity
                </summary>
                <p className="my-2 text-sm">
                    Recorded account labels at the time of each action.{" "}
                    {data.older_events_omitted &&
                        "Showing the latest 200 events."}
                </p>
                {!data.events.length && (
                    <p>No recorded activity is available.</p>
                )}
                <ol className="space-y-2 text-sm">
                    {data.events.map((event) => (
                        <li key={event.id} className="break-words">
                            <time dateTime={event.created_at}>
                                {new Date(event.created_at).toLocaleString()}
                            </time>{" "}
                            · {event.actor_label} ·{" "}
                            {event.action
                                .replace(/^(package|listing)\./, "")
                                .replaceAll("_", " ")}
                        </li>
                    ))}
                </ol>
            </details>
        </section>
    );
}
