import type { PackageFormData } from "../package-form-data";
import type { menuSummary } from "../menu-state";
import type { MenuCategory } from "./package-menu-form";
import type { MeasurementUnit } from "../../catalog/components/measurement-unit-form";
import { purchasedUnit } from "./package-presentation-fields";
import { PresentationReview } from "./presentation-review";

/** Read-only review of unsaved input; this component never calculates authoritative prices. */
export function PackageReview({
    data,
    main,
    summary,
    categories,
    measurementUnits,
}: {
    data: PackageFormData;
    main: boolean;
    summary: ReturnType<typeof menuSummary>;
    categories: MenuCategory[];
    measurementUnits: MeasurementUnit[];
}) {
    return (
        <>
            {" "}
            <h2 className="text-xl font-semibold">Review & Save</h2>
            <h3 className="font-semibold">
                {data.name || "Package name needed"}
            </h3>
            <p>{data.description}</p>
            {!main && (
                <p>
                    {data.presentation
                        ? data.presentation.format === "combined_contents"
                            ? `Every ${purchasedUnit(data.presentation, measurementUnits)} contains the configured combination of ${summary.total} distinct foods. Purchase quantity repeats the complete container, not each food separately.`
                            : `Includes ${summary.total} distinct foods provided as separate servings per complete package set.`
                        : "Legacy Package Format was not recorded; no packaging facts are inferred."}
                </p>
            )}
            <p>
                PHP {data.base_price_pesos || "—"} per{" "}
                {main
                    ? "pax"
                    : data.presentation
                      ? purchasedUnit(data.presentation, measurementUnits)
                      : data.price_basis === "per_pack"
                        ? "pack"
                        : "package"}
                . Minimum {data.minimum_quantity}; increment{" "}
                {data.quantity_step}; maximum{" "}
                {data.maximum_quantity || "not set"}.
            </p>
            <p>
                Exactly {summary.total} distinct foods. Fixed:{" "}
                {summary.fixed.map((f) => f.name).join(", ") || "None"}.
            </p>
            <p>
                Customer Choices:{" "}
                {summary.choiceRules
                    .map((rule) => {
                        const category = categories.find(
                            (item) => item.id === rule.category_id,
                        );
                        return `${category?.name ?? "Food Category"}: ${rule.minimum_count === rule.maximum_count ? `exactly ${rule.minimum_count}` : `${rule.minimum_count} to ${rule.maximum_count}`}`;
                    })
                    .join(" · ") || "None"}
                .
            </p>
            <p>
                Allowed foods:{" "}
                {summary.allowed.map((f) => f.name).join(", ") || "None"}.
            </p>
            <p className="text-sm text-stone-500">
                Review your package details before saving. You can save this
                package as a draft and finish it later, or publish it when
                everything is ready.
            </p>
            {!main && data.serving_default && (
                <p>
                    Default per food: {data.serving_default.quantity}{" "}
                    {data.serving_default.size_label}{" "}
                    {
                        measurementUnits.find(
                            (unit) =>
                                unit.id ===
                                Number(
                                    data.serving_default?.measurement_unit_id,
                                ),
                        )?.abbreviation
                    }
                    . Foods with custom servings keep their overrides.
                </p>
            )}
            {!main && (
                <PresentationReview
                    presentation={data.presentation}
                    foods={summary.allowed}
                    details={data.food_details}
                    sizes={data.food_sizes}
                    servingDefault={data.serving_default}
                    units={measurementUnits}
                />
            )}
            {!main &&
                summary.allowed.some((food) => data.food_sizes[food.id]) && (
                    <section
                        aria-label="Review size choices"
                        className="space-y-3 rounded-xl border p-4"
                    >
                        <h3 className="font-semibold">
                            Size choices per purchased package
                        </h3>
                        {summary.allowed.map((food) => {
                            const group = data.food_sizes[food.id];
                            if (!group) return null;
                            const unit = measurementUnits.find(
                                (unit) =>
                                    unit.id ===
                                    Number(group.measurement_unit_id),
                            );
                            return (
                                <div key={food.id}>
                                    <h4 className="text-sm font-medium">
                                        {food.name}
                                    </h4>
                                    <ul className="text-sm text-slate-600">
                                        {group.choices.map((choice, index) => (
                                            <li key={index}>
                                                {choice.quantity}{" "}
                                                {choice.size_label}{" "}
                                                {unit?.abbreviation ??
                                                    "— choose an active unit"}{" "}
                                                ·{" "}
                                                {choice.is_default
                                                    ? "Included default"
                                                    : `+ PHP ${choice.extra_price_pesos} per package`}
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            );
                        })}
                    </section>
                )}
        </>
    );
}
