import { Link } from "@inertiajs/react";

type Step = {
    label: string;
    detail: string;
    state: "complete" | "current" | "blocked" | "upcoming";
    href?: string;
};

const stateClass: Record<Step["state"], string> = {
    complete: "border-emerald-200 bg-emerald-50 text-emerald-950",
    current: "border-sky-200 bg-sky-50 text-sky-950",
    blocked: "border-amber-200 bg-amber-50 text-amber-950",
    upcoming: "border-stone-200 bg-stone-50 text-stone-700",
};

export function CaseWorkflow({ steps }: { steps: Step[] }) {
    return (
        <nav aria-label="Reservation case progress" className="mb-6">
            <ol className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                {steps.map((step) => (
                    <li
                        key={step.label}
                        className={`rounded-xl border p-4 ${stateClass[step.state]}`}
                    >
                        <p className="text-xs font-bold uppercase tracking-wide">
                            {step.state === "complete"
                                ? "Completed"
                                : step.state === "blocked"
                                  ? "Needs attention"
                                  : step.state === "current"
                                    ? "Current step"
                                    : "Next"}
                        </p>
                        {step.href ? (
                            <Link
                                href={step.href}
                                className="mt-1 block font-semibold underline"
                            >
                                {step.label}
                            </Link>
                        ) : (
                            <p className="mt-1 font-semibold">{step.label}</p>
                        )}
                        <p className="mt-1 text-sm">{step.detail}</p>
                    </li>
                ))}
            </ol>
        </nav>
    );
}
