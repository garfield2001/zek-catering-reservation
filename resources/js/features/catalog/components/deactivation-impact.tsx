import { useEffect, useState } from "react";
import { Link } from "@inertiajs/react";

export function DeactivationImpact(props: {
    kind: "food" | "category" | "unit" | "service";
    id: number;
}) {
    return <ImpactDetails key={`${props.kind}-${props.id}`} {...props} />;
}

function ImpactDetails({
    kind,
    id,
}: {
    kind: "food" | "category" | "unit" | "service";
    id: number;
}) {
    const [data, setData] = useState<{
        messages: string[];
        usage: {
            kind: string;
            version_id: number;
            name: string;
            url: string;
            status: string;
        }[];
    } | null>(null);
    const [failed, setFailed] = useState(false);
    useEffect(() => {
        const controller = new AbortController();
        fetch(`/admin/catalog/deactivation-impact/${kind}/${id}`, {
            headers: { Accept: "application/json" },
            signal: controller.signal,
        })
            .then((response) => {
                if (!response.ok) throw new Error("Impact unavailable");
                return response.json();
            })
            .then(setData)
            .catch(() => {
                if (!controller.signal.aborted) setFailed(true);
            });
        return () => controller.abort();
    }, [kind, id]);
    return (
        <aside
            role="status"
            className="space-y-2 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900"
        >
            <h3 className="font-semibold">Before disabling or archiving</h3>
            {failed ? (
                <p>
                    Impact details could not be loaded. Cancel and reopen this
                    form before deciding; do not assume there are no affected
                    items.
                </p>
            ) : !data ? (
                <p>Checking related catalog records…</p>
            ) : (
                <>
                    {data.messages.map((message) => (
                        <p key={message}>{message}</p>
                    ))}
                    {!!data.usage.length && (
                        <details>
                            <summary className="cursor-pointer">
                                Review related configurations (
                                {data.usage.length})
                            </summary>
                            <ul className="mt-2 space-y-1">
                                {data.usage.map((row) => (
                                    <li key={`${row.kind}-${row.version_id}`}>
                                        <Link
                                            href={row.url}
                                            className="underline"
                                        >
                                            {row.name}
                                        </Link>{" "}
                                        · {row.status}
                                    </li>
                                ))}
                            </ul>
                        </details>
                    )}
                </>
            )}
        </aside>
    );
}
