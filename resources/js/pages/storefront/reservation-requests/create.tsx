import { Link, useForm } from "@inertiajs/react";
import StorefrontLayout from "../../../layouts/storefront-layout";

const endTime = (start: string) => {
    if (!/^\d{2}:\d{2}$/.test(start)) return "Choose a start time";

    const [hours, minutes] = start.split(":").map(Number);
    const end = (hours * 60 + minutes + 240) % (24 * 60);

    return `${String(Math.floor(end / 60)).padStart(2, "0")}:${String(end % 60).padStart(2, "0")}`;
};

export default function CreateReservationRequest({
    selectionCount,
    idempotencyKey,
}: {
    selectionCount: number;
    idempotencyKey: string;
}) {
    const form = useForm({
        idempotency_key: idempotencyKey,
        full_name: "",
        mobile_number: "",
        email: "",
        event_date: "",
        event_start_time: "",
        venue_information: "",
        event_notes: "",
    });
    const errors = form.errors as Record<string, string | undefined>;

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.post("/reservation-request", { preserveScroll: true });
    };

    return (
        <StorefrontLayout
            title="Request catering review"
            selectionCount={selectionCount}
        >
            <div className="mx-auto max-w-3xl px-4 py-10 sm:px-6">
                <p className="text-xs font-bold uppercase tracking-[0.16em] text-amber-700">
                    Planning request
                </p>
                <h1 className="mt-2 font-serif text-4xl font-bold text-emerald-950">
                    Request Staff review
                </h1>
                <p className="mt-4 leading-7 text-stone-600">
                    Send your tentative event details to our team. This is not a
                    confirmed reservation, quotation, or date hold.
                </p>
                <form
                    onSubmit={submit}
                    className="mt-8 space-y-6 rounded-2xl border border-stone-200 bg-white p-5 shadow-sm sm:p-7"
                >
                    <section className="space-y-4">
                        <h2 className="text-lg font-bold text-emerald-950">
                            Your contact details
                        </h2>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <label className="text-sm font-semibold">
                                Full name
                                <input
                                    required
                                    value={form.data.full_name}
                                    onChange={(event) =>
                                        form.setData(
                                            "full_name",
                                            event.target.value,
                                        )
                                    }
                                    className="mt-1 block w-full"
                                />
                                <FieldError error={form.errors.full_name} />
                            </label>
                            <label className="text-sm font-semibold">
                                Mobile number
                                <input
                                    required
                                    inputMode="tel"
                                    value={form.data.mobile_number}
                                    onChange={(event) =>
                                        form.setData(
                                            "mobile_number",
                                            event.target.value,
                                        )
                                    }
                                    className="mt-1 block w-full"
                                />
                                <FieldError error={form.errors.mobile_number} />
                            </label>
                        </div>
                        <label className="block text-sm font-semibold">
                            Email address{" "}
                            <span className="font-normal text-stone-500">
                                (optional)
                            </span>
                            <input
                                type="email"
                                value={form.data.email}
                                onChange={(event) =>
                                    form.setData("email", event.target.value)
                                }
                                className="mt-1 block w-full"
                            />
                            <FieldError error={form.errors.email} />
                        </label>
                    </section>
                    <section className="space-y-4 border-t pt-6">
                        <h2 className="text-lg font-bold text-emerald-950">
                            Tentative event details
                        </h2>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <label className="text-sm font-semibold">
                                Event date
                                <input
                                    required
                                    type="date"
                                    value={form.data.event_date}
                                    onChange={(event) =>
                                        form.setData(
                                            "event_date",
                                            event.target.value,
                                        )
                                    }
                                    className="mt-1 block w-full"
                                />
                                <FieldError error={form.errors.event_date} />
                            </label>
                            <label className="text-sm font-semibold">
                                Start time
                                <input
                                    required
                                    type="time"
                                    value={form.data.event_start_time}
                                    onChange={(event) =>
                                        form.setData(
                                            "event_start_time",
                                            event.target.value,
                                        )
                                    }
                                    className="mt-1 block w-full"
                                />
                                <FieldError
                                    error={form.errors.event_start_time}
                                />
                            </label>
                        </div>
                        <p className="rounded-xl bg-emerald-50 p-4 text-sm text-emerald-950">
                            Catering service is four hours. Your tentative end
                            time is{" "}
                            <strong>
                                {endTime(form.data.event_start_time)}
                            </strong>{" "}
                            (Asia/Manila).
                        </p>
                        <label className="block text-sm font-semibold">
                            Tentative venue information
                            <textarea
                                required
                                value={form.data.venue_information}
                                onChange={(event) =>
                                    form.setData(
                                        "venue_information",
                                        event.target.value,
                                    )
                                }
                                className="mt-1 block w-full"
                                rows={3}
                            />
                            <FieldError error={form.errors.venue_information} />
                        </label>
                        <label className="block text-sm font-semibold">
                            Event notes{" "}
                            <span className="font-normal text-stone-500">
                                (optional)
                            </span>
                            <textarea
                                value={form.data.event_notes}
                                onChange={(event) =>
                                    form.setData(
                                        "event_notes",
                                        event.target.value,
                                    )
                                }
                                className="mt-1 block w-full"
                                rows={3}
                            />
                            <FieldError error={form.errors.event_notes} />
                        </label>
                    </section>
                    {errors.selection && (
                        <p role="alert" className="text-sm text-red-700">
                            {errors.selection}
                        </p>
                    )}
                    <p className="text-sm leading-6 text-stone-600">
                        Online requests require the current minimum advance
                        notice. Contact ZEK Catering for an
                        Administrator-reviewed emergency request. Submission
                        does not guarantee availability or reserve a service
                        slot.
                    </p>
                    <div className="flex flex-wrap gap-3">
                        <button
                            disabled={form.processing}
                            className="rounded-xl bg-emerald-800 px-5 py-3 text-sm font-semibold text-white disabled:opacity-50"
                        >
                            {form.processing ? "Submitting…" : "Submit request"}
                        </button>
                        <Link
                            href="/my-selection"
                            className="rounded-xl border border-emerald-800 px-5 py-3 text-sm font-semibold text-emerald-900"
                        >
                            Back to selection
                        </Link>
                    </div>
                </form>
            </div>
        </StorefrontLayout>
    );
}

function FieldError({ error }: { error?: string }) {
    return error ? (
        <span role="alert" className="mt-1 block text-sm text-red-700">
            {error}
        </span>
    ) : null;
}
