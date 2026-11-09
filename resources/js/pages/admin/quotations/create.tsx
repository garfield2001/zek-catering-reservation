import { router, usePage } from "@inertiajs/react";
import { useState } from "react";
import AdminLayout from "../../../layouts/admin-layout";

type Line = { description: string; quantity: string; unit_price: string };
type FormErrors = Record<string, string>;

export default function CreateManualQuotation() {
    const [lines, setLines] = useState<Line[]>([
        { description: "", quantity: "1", unit_price: "" },
    ]);
    const errors = usePage<{ errors: FormErrors }>().props.errors ?? {};
    const changeLine = (index: number, patch: Partial<Line>) =>
        setLines((current) =>
            current.map((line, position) =>
                position === index ? { ...line, ...patch } : line,
            ),
        );

    return (
        <AdminLayout
            title="New Manual Quotation"
            description="Create an independently priced commercial draft. Laravel validates and calculates submitted peso values in centavos."
            section="quotations"
        >
            <form
                noValidate
                onSubmit={(event) => {
                    event.preventDefault();
                    const data = new FormData(event.currentTarget);
                    router.post("/admin/quotations", {
                        customer_name: data.get("customer_name"),
                        customer_mobile: data.get("customer_mobile"),
                        customer_email: data.get("customer_email"),
                        event_date: data.get("event_date"),
                        event_start_time: data.get("event_start_time"),
                        venue_information: data.get("venue_information"),
                        event_notes: data.get("event_notes"),
                        lines,
                    });
                }}
                className="max-w-3xl space-y-6"
            >
                <section className="rounded-2xl border bg-white p-5">
                    <h2 className="font-semibold">Customer and event</h2>
                    <div className="mt-4 grid gap-4 sm:grid-cols-2">
                        <Field
                            label="Customer name"
                            error={errors.customer_name}
                        >
                            <input
                                name="customer_name"
                                aria-invalid={Boolean(errors.customer_name)}
                                className="mt-1 w-full"
                            />
                        </Field>
                        <Field
                            label="Mobile number"
                            error={errors.customer_mobile}
                        >
                            <input
                                name="customer_mobile"
                                aria-invalid={Boolean(errors.customer_mobile)}
                                className="mt-1 w-full"
                            />
                        </Field>
                        <Field
                            label="Email (optional)"
                            error={errors.customer_email}
                        >
                            <input
                                name="customer_email"
                                type="email"
                                aria-invalid={Boolean(errors.customer_email)}
                                className="mt-1 w-full"
                            />
                        </Field>
                        <Field
                            label="Event date (optional)"
                            error={errors.event_date}
                        >
                            <input
                                name="event_date"
                                type="date"
                                aria-invalid={Boolean(errors.event_date)}
                                className="mt-1 w-full"
                            />
                        </Field>
                        <Field
                            label="Event start (optional)"
                            error={errors.event_start_time}
                        >
                            <input
                                name="event_start_time"
                                type="time"
                                aria-invalid={Boolean(errors.event_start_time)}
                                className="mt-1 w-full"
                            />
                        </Field>
                        <Field
                            label="Venue (optional)"
                            error={errors.venue_information}
                        >
                            <input
                                name="venue_information"
                                aria-invalid={Boolean(errors.venue_information)}
                                className="mt-1 w-full"
                            />
                        </Field>
                    </div>
                    <Field
                        label="Notes (optional)"
                        error={errors.event_notes}
                        className="mt-4"
                    >
                        <textarea
                            name="event_notes"
                            aria-invalid={Boolean(errors.event_notes)}
                            className="mt-1 min-h-20 w-full"
                        />
                    </Field>
                </section>

                <section className="rounded-2xl border bg-white p-5">
                    <h2 className="font-semibold">Manual line items</h2>
                    <p className="mt-1 text-sm text-slate-600">
                        Use for independently agreed commercial items. Prices
                        remain a draft until the quotation is issued with
                        approved terms.
                    </p>
                    {errors.lines && <FieldError error={errors.lines} />}
                    <div className="mt-4 space-y-3">
                        {lines.map((line, index) => (
                            <div
                                key={index}
                                className="rounded-xl bg-slate-50 p-3"
                            >
                                <div className="grid gap-2 sm:grid-cols-[1fr_8rem_10rem_auto]">
                                    <Field
                                        error={
                                            errors[`lines.${index}.description`]
                                        }
                                    >
                                        <input
                                            name={`lines.${index}.description`}
                                            placeholder="Description"
                                            value={line.description}
                                            onChange={(event) =>
                                                changeLine(index, {
                                                    description:
                                                        event.target.value,
                                                })
                                            }
                                            aria-invalid={Boolean(
                                                errors[
                                                    `lines.${index}.description`
                                                ],
                                            )}
                                        />
                                    </Field>
                                    <Field
                                        error={
                                            errors[`lines.${index}.quantity`]
                                        }
                                    >
                                        <input
                                            name={`lines.${index}.quantity`}
                                            type="number"
                                            min="1"
                                            placeholder="Quantity"
                                            value={line.quantity}
                                            onChange={(event) =>
                                                changeLine(index, {
                                                    quantity:
                                                        event.target.value,
                                                })
                                            }
                                            aria-invalid={Boolean(
                                                errors[
                                                    `lines.${index}.quantity`
                                                ],
                                            )}
                                        />
                                    </Field>
                                    <Field
                                        error={
                                            errors[`lines.${index}.unit_price`]
                                        }
                                    >
                                        <input
                                            name={`lines.${index}.unit_price`}
                                            inputMode="decimal"
                                            placeholder="PHP unit price"
                                            value={line.unit_price}
                                            onChange={(event) =>
                                                changeLine(index, {
                                                    unit_price:
                                                        event.target.value,
                                                })
                                            }
                                            aria-invalid={Boolean(
                                                errors[
                                                    `lines.${index}.unit_price`
                                                ],
                                            )}
                                        />
                                    </Field>
                                    {lines.length > 1 && (
                                        <button
                                            type="button"
                                            onClick={() =>
                                                setLines((current) =>
                                                    current.filter(
                                                        (_, position) =>
                                                            position !== index,
                                                    ),
                                                )
                                            }
                                            className="rounded-lg border px-3 text-sm"
                                        >
                                            Remove
                                        </button>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                    <button
                        type="button"
                        onClick={() =>
                            setLines((current) => [
                                ...current,
                                {
                                    description: "",
                                    quantity: "1",
                                    unit_price: "",
                                },
                            ])
                        }
                        className="mt-3 rounded-lg border border-emerald-800 px-3 py-2 text-sm font-semibold text-emerald-900"
                    >
                        Add line
                    </button>
                </section>
                <button className="rounded-lg bg-emerald-800 px-5 py-3 text-sm font-semibold text-white">
                    Create quotation draft
                </button>
            </form>
        </AdminLayout>
    );
}

function Field({
    label,
    error,
    className,
    children,
}: {
    label?: string;
    error?: string;
    className?: string;
    children: React.ReactNode;
}) {
    return (
        <label className={`block text-sm font-medium ${className ?? ""}`}>
            {label}
            {children}
            <FieldError error={error} />
        </label>
    );
}

function FieldError({ error }: { error?: string }) {
    return error ? (
        <span role="alert" className="mt-1 block text-sm text-rose-700">
            {error}
        </span>
    ) : null;
}
