import { useState } from "react";
import { Button } from "../../../components/ui/button";
import { packagePaths } from "../paths";
import type {
    VariantVersion,
    SelectionGroup,
    PackageProduct,
    PackageVariant,
    Preview,
} from "../types";

type Allocation = { quantity: number; selections: Record<string, number[]> };
const defaults = (groups: SelectionGroup[]): Record<string, number[]> =>
    Object.fromEntries(
        groups
            .filter((group) => group.mode !== "fixed")
            .map((group) => [
                group.group_code,
                group.options
                    ?.filter((option) => option.is_default)
                    .map((option) => option.offering_option_id) ?? [],
            ]),
    );
const formatMinor = (minor: string) => {
    const amount = BigInt(minor);
    return `₱${(amount / 100n).toLocaleString()}.${(amount % 100n).toString().padStart(2, "0")}`;
};

export function PreviewBuilder({
    product,
    variant,
    version,
}: {
    product: PackageProduct;
    variant: PackageVariant;
    version: VariantVersion;
}) {
    const groups = version.groups ?? [];
    const [quantity, setQuantity] = useState(version.minimum_quantity);
    const [allocations, setAllocations] = useState<Allocation[]>([
        { quantity: version.minimum_quantity, selections: defaults(groups) },
    ]);
    const [preview, setPreview] = useState<Preview | null>(null);
    const [error, setError] = useState("");
    const [processing, setProcessing] = useState(false);
    const update = (index: number, changes: Partial<Allocation>) =>
        setAllocations((current) =>
            current.map((group, offset) =>
                offset === index ? { ...group, ...changes } : group,
            ),
        );
    const setSelection = (index: number, code: string, ids: number[]) =>
        update(index, {
            selections: { ...allocations[index].selections, [code]: ids },
        });
    const submit = async () => {
        setProcessing(true);
        setPreview(null);
        setError("");
        try {
            const response = await fetch(
                packagePaths.version(product.id, variant.id, version.id) +
                    "/preview",
                {
                    method: "POST",
                    credentials: "same-origin",
                    headers: {
                        "Content-Type": "application/json",
                        Accept: "application/json",
                        "X-CSRF-TOKEN":
                            document.querySelector<HTMLMetaElement>(
                                'meta[name="csrf-token"]',
                            )?.content ?? "",
                    },
                    body: JSON.stringify({ quantity, groups: allocations }),
                },
            );
            const result = await response.json();
            if (!response.ok) {
                const messages = Object.values(result.errors ?? {}).flat();
                setError(
                    messages.length
                        ? messages.join(" ")
                        : "Preview failed. Refresh and try again.",
                );
                return;
            }
            setPreview(result as Preview);
        } catch {
            setError("Could not reach the server. No price was calculated.");
        } finally {
            setProcessing(false);
        }
    };
    return (
        <section className="space-y-5 rounded-xl border border-amber-200 bg-amber-50 p-5">
            <div>
                <h2 className="text-lg font-semibold">
                    Draft configuration preview
                </h2>
                <p className="text-sm text-amber-900">
                    Admin-only preview; this is not a published price,
                    reservation quote or bookable offer.
                </p>
            </div>
            <label className="block max-w-xs text-sm">
                Total{" "}
                {version.price_basis === "per_pax"
                    ? "guests"
                    : version.price_basis === "per_pack"
                      ? "packs"
                      : "sets"}
                <input
                    type="number"
                    min="1"
                    className="mt-1"
                    value={quantity}
                    onChange={(e) => {
                        const value = Number(e.target.value);
                        setQuantity(value);
                        if (version.selection_policy === "uniform")
                            update(0, { quantity: value });
                    }}
                />
            </label>
            {allocations.map((allocation, index) => (
                <div
                    key={index}
                    className="space-y-4 rounded-lg border border-amber-200 bg-white p-4"
                >
                    <div className="flex items-center justify-between">
                        <h3 className="font-semibold">
                            {version.selection_policy === "split"
                                ? `Meal group ${index + 1}`
                                : "Uniform selections"}
                        </h3>
                        {version.selection_policy === "split" &&
                            allocations.length > 1 && (
                                <button
                                    type="button"
                                    className="text-sm text-red-700 underline"
                                    onClick={() =>
                                        setAllocations((current) =>
                                            current.filter(
                                                (_, offset) => offset !== index,
                                            ),
                                        )
                                    }
                                >
                                    Remove group
                                </button>
                            )}
                    </div>
                    {version.selection_policy === "split" && (
                        <label className="text-sm">
                            Packs in this group
                            <input
                                type="number"
                                min="1"
                                value={allocation.quantity}
                                onChange={(e) =>
                                    update(index, {
                                        quantity: Number(e.target.value),
                                    })
                                }
                            />
                        </label>
                    )}
                    {groups.map((group) => (
                        <div key={group.id} className="space-y-2">
                            <h4 className="text-sm font-semibold">
                                {group.name}{" "}
                                <span className="font-normal text-stone-500">
                                    ({group.minimum_count}–{group.maximum_count}
                                    )
                                </span>
                            </h4>
                            {group.mode === "fixed" ? (
                                <p className="text-sm text-stone-600">
                                    Included:{" "}
                                    {
                                        group.options?.[0]?.offering_option
                                            ?.offering?.dish?.name
                                    }{" "}
                                    /{" "}
                                    {group.options?.[0]?.offering_option?.label}
                                    ; {group.inclusion_count} per purchased unit
                                </p>
                            ) : (
                                <div className="grid gap-2 sm:grid-cols-2">
                                    {group.options?.map((option) => {
                                        const chosen =
                                            allocation.selections[
                                                group.group_code
                                            ] ?? [];
                                        const checked = chosen.includes(
                                            option.offering_option_id,
                                        );
                                        return (
                                            <label
                                                key={option.id}
                                                className="flex items-center gap-2 rounded border border-stone-200 p-2 text-sm"
                                            >
                                                <input
                                                    type={
                                                        group.mode ===
                                                        "select_one"
                                                            ? "radio"
                                                            : "checkbox"
                                                    }
                                                    name={
                                                        group.mode ===
                                                        "select_one"
                                                            ? `group-${index}-${group.id}`
                                                            : undefined
                                                    }
                                                    checked={checked}
                                                    onChange={(e) =>
                                                        setSelection(
                                                            index,
                                                            group.group_code,
                                                            group.mode ===
                                                                "select_one"
                                                                ? [
                                                                      option.offering_option_id,
                                                                  ]
                                                                : e.target
                                                                        .checked
                                                                  ? [
                                                                        ...chosen,
                                                                        option.offering_option_id,
                                                                    ]
                                                                  : chosen.filter(
                                                                        (id) =>
                                                                            id !==
                                                                            option.offering_option_id,
                                                                    ),
                                                        )
                                                    }
                                                />
                                                {
                                                    option.offering_option
                                                        ?.offering?.dish?.name
                                                }{" "}
                                                /{" "}
                                                {option.offering_option?.label}
                                            </label>
                                        );
                                    })}
                                </div>
                            )}
                        </div>
                    ))}
                </div>
            ))}
            {version.selection_policy === "split" && (
                <button
                    type="button"
                    className="text-sm font-semibold text-emerald-800 underline"
                    onClick={() =>
                        setAllocations((current) => [
                            ...current,
                            { quantity: 1, selections: defaults(groups) },
                        ])
                    }
                >
                    Add meal group
                </button>
            )}
            {error && (
                <p role="alert" className="text-sm text-red-800">
                    {error}
                </p>
            )}
            <Button type="button" disabled={processing} onClick={submit}>
                {processing ? "Calculating…" : "Calculate on server"}
            </Button>
            {preview && (
                <div
                    role="status"
                    className="space-y-2 rounded-lg bg-white p-4 text-sm"
                >
                    <p className="font-semibold">
                        Draft preview: {formatMinor(preview.total_minor)} total
                    </p>
                    <p>
                        Quantity rule: minimum{" "}
                        {preview.effective_quantity_rules.minimum_quantity},
                        increment{" "}
                        {preview.effective_quantity_rules.quantity_step},
                        maximum{" "}
                        {preview.effective_quantity_rules.maximum_quantity ??
                            "none"}
                    </p>
                    {preview.groups.map((line, index) => (
                        <div
                            key={index}
                            className="border-t border-stone-200 pt-2"
                        >
                            <p>
                                {line.quantity} × base{" "}
                                {formatMinor(line.unit_base_minor)} ={" "}
                                {formatMinor(line.base_subtotal_minor)}
                            </p>
                            {line.adjustments.map((adjustment, offset) => (
                                <p key={offset}>
                                    Premium {adjustment.group_code}:{" "}
                                    {formatMinor(adjustment.unit_amount_minor)}{" "}
                                    per unit,{" "}
                                    {formatMinor(adjustment.line_amount_minor)}{" "}
                                    line
                                </p>
                            ))}
                            <p className="font-medium">
                                Group total:{" "}
                                {formatMinor(line.line_total_minor)}
                            </p>
                        </div>
                    ))}
                </div>
            )}
        </section>
    );
}
