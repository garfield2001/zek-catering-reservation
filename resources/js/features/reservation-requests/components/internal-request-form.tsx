import { useForm } from "@inertiajs/react";
import { useMemo, useState } from "react";
import { Check, Trash2 } from "lucide-react";
import { PackageConfigurator } from "../../storefront/components/package-configurator";
import type {
    PricePreview,
    PublicListing,
    PublicPackage,
} from "../../storefront/types";
import { csrfFetch } from "../../../lib/http";
import { validationErrors } from "../../../lib/validation-errors";
import { formatPesos } from "../../packages/money";

type PackageSelection = {
    type: "package";
    product_id: number;
    version_id: number;
    quantity: number;
    groups: Array<{
        quantity: number;
        selections: Record<string, number[] | Record<string, number>>;
    }>;
    total_minor: string;
    name: string;
    role: "main" | "addon";
};

type ListingSelection = {
    type: "listing";
    listing_id: number;
    version_id: number;
    quantity: number;
    selling_option_id: number | null;
    total_minor: string;
    name: string;
};

type Selection = PackageSelection | ListingSelection;

type Catalog = {
    main_packages: PublicPackage[];
    add_on_services: Array<{
        id: number;
        name: string;
        description: string | null;
        packages: PublicPackage[];
        listings: PublicListing[];
    }>;
};

type InitialDetails = {
    full_name?: string | null;
    mobile_number?: string | null;
    email?: string | null;
    event_date?: string | null;
    event_start_time?: string | null;
    venue_information?: string | null;
    event_notes?: string | null;
};

type Props = {
    catalog: Catalog;
    endpoint: string;
    idempotencyKey: string;
    initial?: InitialDetails;
    title?: string;
    description?: string;
};

type RequestForm = {
    idempotency_key: string;
    full_name: string;
    mobile_number: string;
    email: string;
    event_date: string;
    event_start_time: string;
    venue_information: string;
    event_notes: string;
};

function FieldError({ error }: { error?: string }) {
    return error ? (
        <p role="alert" className="mt-1 text-sm text-rose-700">
            {error}
        </p>
    ) : null;
}

function selectionWireFormat(selection: Selection): Record<string, unknown> {
    if (selection.type === "package") {
        return {
            type: "package",
            product_id: selection.product_id,
            version_id: selection.version_id,
            quantity: selection.quantity,
            groups: selection.groups,
        };
    }

    return {
        type: "listing",
        listing_id: selection.listing_id,
        version_id: selection.version_id,
        quantity: selection.quantity,
        selling_option_id: selection.selling_option_id,
    };
}

export function InternalRequestForm({
    catalog,
    endpoint,
    idempotencyKey,
    initial = {},
    title = "Create reservation request",
    description = "Build a request from currently published offerings. Saving it does not reserve an event date or confirm availability.",
}: Props) {
    const form = useForm<RequestForm>({
        idempotency_key: idempotencyKey,
        full_name: initial.full_name ?? "",
        mobile_number: initial.mobile_number ?? "",
        email: initial.email ?? "",
        event_date: initial.event_date ?? "",
        event_start_time: initial.event_start_time?.slice(0, 5) ?? "",
        venue_information: initial.venue_information ?? "",
        event_notes: initial.event_notes ?? "",
    });
    const [selections, setSelections] = useState<Selection[]>([]);
    const [expandedMain, setExpandedMain] = useState<number | null>(
        catalog.main_packages[0]?.id ?? null,
    );
    const total = useMemo(
        () =>
            selections.reduce(
                (sum, selection) => sum + BigInt(selection.total_minor),
                0n,
            ),
        [selections],
    );
    const main = selections.find(
        (selection): selection is PackageSelection =>
            selection.type === "package" && selection.role === "main",
    );

    function replaceSelection(next: Selection) {
        setSelections((current) => {
            const withoutMatching = current.filter((selection) => {
                if (next.type === "package" && next.role === "main") {
                    return !(
                        selection.type === "package" &&
                        selection.role === "main"
                    );
                }
                if (next.type === "package") {
                    return !(
                        selection.type === "package" &&
                        selection.product_id === next.product_id
                    );
                }

                return !(
                    selection.type === "listing" &&
                    selection.listing_id === next.listing_id &&
                    selection.selling_option_id === next.selling_option_id
                );
            });
            const updated = [...withoutMatching, next];
            form.clearErrors();

            return updated;
        });
    }

    function removeSelection(index: number) {
        setSelections((current) => {
            const updated = current.filter(
                (_, itemIndex) => itemIndex !== index,
            );
            return updated;
        });
    }

    function submit(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            selections: selections.map(selectionWireFormat),
        }));
        form.post(endpoint, { preserveScroll: true });
    }

    return (
        <form onSubmit={submit} className="space-y-7">
            <header className="max-w-3xl">
                <h1 className="text-3xl font-bold text-emerald-950">{title}</h1>
                <p className="mt-2 text-sm leading-6 text-slate-600">
                    {description}
                </p>
            </header>

            <section className="rounded-2xl border bg-white p-5">
                <h2 className="font-semibold text-emerald-950">
                    Customer and event details
                </h2>
                <div className="mt-4 grid gap-4 md:grid-cols-2">
                    <label className="text-sm font-medium">
                        Customer name
                        <input
                            value={form.data.full_name}
                            onChange={(event) =>
                                form.setData("full_name", event.target.value)
                            }
                            aria-invalid={Boolean(form.errors.full_name)}
                            className="mt-1 w-full"
                        />
                    </label>
                    <label className="text-sm font-medium">
                        Mobile number
                        <input
                            value={form.data.mobile_number}
                            onChange={(event) =>
                                form.setData(
                                    "mobile_number",
                                    event.target.value,
                                )
                            }
                            aria-invalid={Boolean(form.errors.mobile_number)}
                            className="mt-1 w-full"
                        />
                    </label>
                    <label className="text-sm font-medium">
                        Email address{" "}
                        <span className="font-normal text-slate-500">
                            (optional)
                        </span>
                        <input
                            type="email"
                            value={form.data.email}
                            onChange={(event) =>
                                form.setData("email", event.target.value)
                            }
                            aria-invalid={Boolean(form.errors.email)}
                            className="mt-1 w-full"
                        />
                    </label>
                    <label className="text-sm font-medium">
                        Event date
                        <input
                            type="date"
                            value={form.data.event_date}
                            onChange={(event) =>
                                form.setData("event_date", event.target.value)
                            }
                            aria-invalid={Boolean(form.errors.event_date)}
                            className="mt-1 w-full"
                        />
                    </label>
                    <label className="text-sm font-medium">
                        Event start time
                        <input
                            type="time"
                            value={form.data.event_start_time}
                            onChange={(event) =>
                                form.setData(
                                    "event_start_time",
                                    event.target.value,
                                )
                            }
                            aria-invalid={Boolean(form.errors.event_start_time)}
                            className="mt-1 w-full"
                        />
                    </label>
                    <label className="text-sm font-medium">
                        Venue
                        <input
                            value={form.data.venue_information}
                            onChange={(event) =>
                                form.setData(
                                    "venue_information",
                                    event.target.value,
                                )
                            }
                            aria-invalid={Boolean(
                                form.errors.venue_information,
                            )}
                            className="mt-1 w-full"
                        />
                    </label>
                </div>
                <label className="mt-4 block text-sm font-medium">
                    Internal notes{" "}
                    <span className="font-normal text-slate-500">
                        (optional)
                    </span>
                    <textarea
                        value={form.data.event_notes}
                        onChange={(event) =>
                            form.setData("event_notes", event.target.value)
                        }
                        className="mt-1 w-full"
                        rows={3}
                    />
                </label>
                <div className="mt-2 grid gap-1 md:grid-cols-2">
                    {[
                        "full_name",
                        "mobile_number",
                        "email",
                        "event_date",
                        "event_start_time",
                        "venue_information",
                        "event_notes",
                    ].map((field) => (
                        <FieldError
                            key={field}
                            error={
                                form.errors[field as keyof typeof form.errors]
                            }
                        />
                    ))}
                </div>
            </section>

            <section className="rounded-2xl border bg-white p-5">
                <h2 className="font-semibold text-emerald-950">
                    Main Catering package
                </h2>
                <p className="mt-1 text-sm text-slate-600">
                    Choose and validate exactly one currently published Main
                    Catering package. Its food choices and price are checked
                    again when the request is saved.
                </p>
                {catalog.main_packages.length === 0 ? (
                    <p className="mt-4 rounded-xl bg-amber-50 p-4 text-sm text-amber-950">
                        No published Main Catering package is currently
                        available. Publish an eligible package before creating a
                        request.
                    </p>
                ) : (
                    <div className="mt-4 space-y-4">
                        {catalog.main_packages.map((offering) => (
                            <details
                                key={offering.id}
                                open={expandedMain === offering.id}
                                onToggle={(event) => {
                                    if (
                                        (
                                            event.currentTarget as HTMLDetailsElement
                                        ).open
                                    )
                                        setExpandedMain(offering.id);
                                }}
                                className="rounded-xl border border-stone-200 p-3"
                            >
                                <summary className="cursor-pointer font-semibold text-emerald-950">
                                    {offering.name}
                                    {main?.product_id === offering.id
                                        ? " — selected"
                                        : ""}
                                </summary>
                                <div className="mt-4">
                                    <PackageConfigurator
                                        offering={offering}
                                        previewUrl={`/catering-packages/${offering.id}/preview`}
                                        selectionLabel={`Use ${offering.name}`}
                                        onConfigurationSaved={(
                                            configuration,
                                            preview,
                                        ) =>
                                            replaceSelection({
                                                type: "package",
                                                role: "main",
                                                product_id: offering.id,
                                                version_id: offering.version_id,
                                                quantity:
                                                    configuration.quantity,
                                                groups: configuration.groups,
                                                total_minor:
                                                    preview.total_minor,
                                                name: offering.name,
                                            })
                                        }
                                    />
                                </div>
                            </details>
                        ))}
                    </div>
                )}
            </section>

            <section className="rounded-2xl border bg-white p-5">
                <h2 className="font-semibold text-emerald-950">
                    Optional add-ons
                </h2>
                <p className="mt-1 text-sm text-slate-600">
                    Add published packages or individually priced items only
                    when needed. Each saved configuration remains separate from
                    the customer-facing browser selection.
                </p>
                {catalog.add_on_services.length === 0 ? (
                    <p className="mt-4 text-sm text-slate-600">
                        No optional add-ons are currently published.
                    </p>
                ) : (
                    catalog.add_on_services.map((service) => (
                        <details
                            key={service.id}
                            className="mt-4 rounded-xl border border-stone-200 p-4"
                        >
                            <summary className="cursor-pointer font-semibold text-emerald-950">
                                {service.name}
                            </summary>
                            {service.description && (
                                <p className="mt-2 text-sm text-slate-600">
                                    {service.description}
                                </p>
                            )}
                            <div className="mt-4 space-y-4">
                                {service.packages.map((offering) => (
                                    <details
                                        key={offering.id}
                                        className="rounded-lg bg-stone-50 p-3"
                                    >
                                        <summary className="cursor-pointer font-medium">
                                            Configure {offering.name}
                                        </summary>
                                        <div className="mt-3">
                                            <PackageConfigurator
                                                offering={offering}
                                                previewUrl={`/add-on-services/${service.id}/packages/${offering.id}/preview`}
                                                selectionLabel={`Add ${offering.name}`}
                                                onConfigurationSaved={(
                                                    configuration,
                                                    preview,
                                                ) =>
                                                    replaceSelection({
                                                        type: "package",
                                                        role: "addon",
                                                        product_id: offering.id,
                                                        version_id:
                                                            offering.version_id,
                                                        quantity:
                                                            configuration.quantity,
                                                        groups: configuration.groups,
                                                        total_minor:
                                                            preview.total_minor,
                                                        name: offering.name,
                                                    })
                                                }
                                            />
                                        </div>
                                    </details>
                                ))}
                                {service.listings.map((listing) => (
                                    <InternalListingSelector
                                        key={listing.id}
                                        listing={listing}
                                        previewUrl={`/add-on-services/${service.id}/foods/${listing.id}/preview`}
                                        onSave={(selection) =>
                                            replaceSelection(selection)
                                        }
                                    />
                                ))}
                            </div>
                        </details>
                    ))
                )}
            </section>

            <section className="rounded-2xl border bg-white p-5">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h2 className="font-semibold text-emerald-950">
                            Request summary
                        </h2>
                        <p className="mt-1 text-sm text-slate-600">
                            The displayed amount is an estimate. Laravel
                            revalidates the selected published versions,
                            choices, availability, and price at submission.
                        </p>
                    </div>
                    <p className="text-2xl font-bold text-emerald-950">
                        {formatPesos(total.toString())}
                    </p>
                </div>
                {selections.length > 0 ? (
                    <ul className="mt-4 space-y-2">
                        {selections.map((selection, index) => (
                            <li
                                key={`${selection.type}-${selection.name}-${index}`}
                                className="flex items-center justify-between gap-3 rounded-lg bg-slate-50 p-3 text-sm"
                            >
                                <span>
                                    <strong>{selection.name}</strong>
                                    <span className="ml-2 text-slate-500">
                                        {selection.type === "package"
                                            ? `${selection.quantity} quantity`
                                            : `${selection.quantity} ${selection.selling_option_id ? "selected option" : "base option"}`}
                                    </span>
                                </span>
                                <span className="flex items-center gap-3">
                                    <strong>
                                        {formatPesos(selection.total_minor)}
                                    </strong>
                                    <button
                                        type="button"
                                        onClick={() => removeSelection(index)}
                                        className="text-rose-700"
                                        aria-label={`Remove ${selection.name}`}
                                    >
                                        <Trash2 size={17} />
                                    </button>
                                </span>
                            </li>
                        ))}
                    </ul>
                ) : (
                    <p className="mt-4 text-sm text-slate-600">
                        No offerings are configured yet.
                    </p>
                )}
                <FieldError
                    error={
                        (form.errors as Record<string, string | undefined>)
                            .selections ??
                        (form.errors as Record<string, string | undefined>)
                            .selection
                    }
                />
                <button
                    type="submit"
                    disabled={form.processing || !main}
                    className="mt-5 inline-flex items-center gap-2 rounded-xl bg-emerald-800 px-5 py-3 font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <Check size={18} />{" "}
                    {form.processing
                        ? "Saving request…"
                        : "Create reservation request"}
                </button>
                {!main && (
                    <p className="mt-2 text-sm text-amber-800">
                        Configure one Main Catering package before saving.
                    </p>
                )}
            </section>
        </form>
    );
}

function InternalListingSelector({
    listing,
    previewUrl,
    onSave,
}: {
    listing: PublicListing;
    previewUrl: string;
    onSave: (selection: ListingSelection) => void;
}) {
    const [quantity, setQuantity] = useState(listing.minimum_quantity);
    const [optionId, setOptionId] = useState<number | null>(
        listing.selling_options.length === 1
            ? listing.selling_options[0].id
            : null,
    );
    const [error, setError] = useState<string | null>(null);
    const [saving, setSaving] = useState(false);

    async function save() {
        setSaving(true);
        setError(null);
        try {
            const response = await csrfFetch(previewUrl, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ quantity, selling_option_id: optionId }),
            });
            const body: unknown = await response.json();
            if (!response.ok) {
                setError(
                    Object.values(validationErrors(body))[0] ??
                        "This item could not be validated.",
                );
                return;
            }
            const preview = body as PricePreview;
            onSave({
                type: "listing",
                listing_id: listing.id,
                version_id: listing.version_id,
                quantity,
                selling_option_id: optionId,
                total_minor: preview.total_minor,
                name: listing.name,
            });
        } catch {
            setError("This item could not be validated. Please try again.");
        } finally {
            setSaving(false);
        }
    }

    return (
        <div className="rounded-lg bg-stone-50 p-3">
            <p className="font-medium">{listing.name}</p>
            <div className="mt-2 grid gap-3 sm:grid-cols-3">
                <label className="text-sm">
                    Quantity
                    <input
                        type="number"
                        min={listing.minimum_quantity}
                        step={listing.quantity_step}
                        value={quantity}
                        onChange={(event) =>
                            setQuantity(Number(event.target.value))
                        }
                        className="mt-1 w-full"
                    />
                </label>
                {listing.selling_options.length > 0 && (
                    <label className="text-sm">
                        Selling option
                        <select
                            value={optionId ?? ""}
                            onChange={(event) =>
                                setOptionId(
                                    event.target.value
                                        ? Number(event.target.value)
                                        : null,
                                )
                            }
                            className="mt-1 w-full"
                        >
                            {listing.selling_options.length !== 1 && (
                                <option value="">Choose an option</option>
                            )}
                            {listing.selling_options.map((option) => (
                                <option key={option.id} value={option.id}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                    </label>
                )}
                <button
                    type="button"
                    onClick={() => void save()}
                    disabled={saving}
                    className="self-end rounded-lg border border-emerald-800 px-3 py-2 text-sm font-semibold text-emerald-900"
                >
                    {saving ? "Checking…" : "Add item"}
                </button>
            </div>
            {error && (
                <p role="alert" className="mt-2 text-sm text-rose-700">
                    {error}
                </p>
            )}
        </div>
    );
}
