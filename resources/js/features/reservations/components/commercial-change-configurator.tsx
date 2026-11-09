import { useState } from "react";
import { PackageConfigurator } from "../../storefront/components/package-configurator";
import type { PublicListing, PublicPackage } from "../../storefront/types";

export type MenuSelection = {
    groups: Array<{
        quantity: number;
        selections: Record<string, number[] | Record<string, number>>;
    }>;
};

export type AddonSelection = Array<
    | {
          type: "package";
          product_id: number;
          quantity: number;
          groups: MenuSelection["groups"];
      }
    | {
          type: "listing";
          listing_id: number;
          quantity: number;
          selling_option_id: number | null;
      }
>;

export type ChangeAddonOffering = {
    id: number;
    name: string;
    packages: PublicPackage[];
    listings: PublicListing[];
};

type Props = {
    offerings: ChangeAddonOffering[];
    selection: AddonSelection | null;
    onChange: (selection: AddonSelection) => void;
};

/**
 * Collects a proposed replacement set only. Laravel validates availability,
 * serving choices, and prices again when the reservation change is submitted.
 */
export function CommercialChangeAddonConfigurator({
    offerings,
    selection,
    onChange,
}: Props) {
    const selected = selection ?? [];
    const replacePackage = (
        productId: number,
        quantity: number,
        groups: MenuSelection["groups"],
    ) => {
        onChange([
            ...selected.filter(
                (item) =>
                    item.type !== "package" || item.product_id !== productId,
            ),
            { type: "package", product_id: productId, quantity, groups },
        ]);
    };
    const replaceListing = (
        listingId: number,
        quantity: number,
        sellingOptionId: number | null,
    ) => {
        onChange([
            ...selected.filter(
                (item) =>
                    item.type !== "listing" || item.listing_id !== listingId,
            ),
            {
                type: "listing",
                listing_id: listingId,
                quantity,
                selling_option_id: sellingOptionId,
            },
        ]);
    };

    return (
        <details className="rounded-xl border border-stone-200 p-3">
            <summary className="cursor-pointer font-semibold text-emerald-950">
                Replace optional add-ons
            </summary>
            <p className="mt-2 text-sm text-stone-600">
                This replaces the optional add-ons on the revised quotation.
                Current prices and availability are checked again by Laravel.
            </p>
            <button
                type="button"
                onClick={() => onChange([])}
                className="mt-3 rounded-lg border border-rose-300 px-3 py-2 text-sm font-semibold text-rose-800"
            >
                Remove all optional add-ons
            </button>
            {offerings.map((service) => (
                <section key={service.id} className="mt-5 border-t pt-4">
                    <h3 className="font-semibold">{service.name}</h3>
                    {service.packages.map((offering) => (
                        <details
                            key={offering.id}
                            className="mt-3 rounded-lg bg-stone-50 p-3"
                        >
                            <summary className="cursor-pointer font-medium">
                                Configure {offering.name}
                            </summary>
                            <PackageConfigurator
                                offering={offering}
                                previewUrl={`/add-on-services/${service.id}/packages/${offering.id}/preview`}
                                selectionLabel={`Use ${offering.name}`}
                                onConfigurationSaved={(configuration) =>
                                    replacePackage(
                                        offering.id,
                                        configuration.quantity,
                                        configuration.groups,
                                    )
                                }
                            />
                        </details>
                    ))}
                    {service.listings.map((listing) => (
                        <ListingChangeSelector
                            key={listing.id}
                            listing={listing}
                            onSave={replaceListing}
                        />
                    ))}
                </section>
            ))}
            {selection !== null && (
                <p className="mt-4 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-950">
                    {selection.length === 0
                        ? "All optional add-ons will be removed in the proposed revision."
                        : `${selection.length} proposed optional add-on${selection.length === 1 ? "" : "s"} ready for review.`}
                </p>
            )}
        </details>
    );
}

function ListingChangeSelector({
    listing,
    onSave,
}: {
    listing: PublicListing;
    onSave: (
        listingId: number,
        quantity: number,
        sellingOptionId: number | null,
    ) => void;
}) {
    const [quantity, setQuantity] = useState(listing.minimum_quantity);
    const [optionId, setOptionId] = useState<number | null>(
        listing.selling_options.length === 1
            ? listing.selling_options[0].id
            : null,
    );

    return (
        <div className="mt-3 rounded-lg bg-stone-50 p-3">
            <p className="font-medium">{listing.name}</p>
            <div className="mt-2 grid gap-2 sm:grid-cols-3">
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
                                    event.target.value === ""
                                        ? null
                                        : Number(event.target.value),
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
                    onClick={() => onSave(listing.id, quantity, optionId)}
                    className="self-end rounded-lg border border-emerald-800 px-3 py-2 text-sm font-semibold text-emerald-900"
                >
                    Use item
                </button>
            </div>
        </div>
    );
}
