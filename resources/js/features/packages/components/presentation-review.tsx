import type { MeasurementUnit } from "../../catalog/components/measurement-unit-form";
import type { FoodDetail } from "./menu-food-details";
import type { FoodSizes } from "./food-size-fields";
import type { ServingDefault } from "./serving-default-fields";
import type { PackagePresentationInput } from "./package-presentation-fields";

export function PresentationReview({
    presentation,
    foods,
    details,
    sizes,
    servingDefault,
    units,
}: {
    presentation: PackagePresentationInput | null;
    foods: { id: number; name: string }[];
    details: Record<number, FoodDetail>;
    sizes: FoodSizes;
    servingDefault: ServingDefault | null;
    units: MeasurementUnit[];
}) {
    return (
        <section className="space-y-2">
            <p className="text-sm text-slate-600">
                Serving and presentation facts below apply only to foods
                included or selected in the final menu. Allowed alternatives do
                not add extra foods or containers.
            </p>
            <ul
                aria-label="Review food serving and presentation"
                className="list-inside list-disc text-sm"
            >
                {foods.map((food) => {
                    const value =
                        presentation?.food_overrides[food.id] ?? presentation;
                    const unit = units.find(
                        (unit) =>
                            unit.id === Number(value?.measurement_unit_id),
                    );
                    const detail = details[food.id];
                    const size = sizes[food.id];
                    const defaultSize = size?.choices.find(
                        (choice) => choice.is_default,
                    );
                    const serving = size
                        ? `${defaultSize?.quantity ?? ""} ${defaultSize?.size_label ?? ""} ${units.find((unit) => unit.id === Number(size.measurement_unit_id))?.abbreviation ?? ""}`
                        : detail?.included_quantity
                          ? `${detail.included_quantity} ${detail.included_unit}`
                          : servingDefault
                            ? `${servingDefault.quantity} ${servingDefault.size_label ?? ""} ${units.find((unit) => unit.id === Number(servingDefault.measurement_unit_id))?.abbreviation ?? ""}`
                            : null;
                    return (
                        <li key={food.id}>
                            {food.name}:{" "}
                            {presentation?.format === "combined_contents"
                                ? "component of each complete purchased container"
                                : value?.quantity && unit
                                  ? `presented in ${value.quantity} ${unit.name}`
                                  : "presentation not specified"}
                            {serving
                                ? `; included serving ${serving}`
                                : "; serving amount not specified"}
                            {!size &&
                            Number(detail?.price_adjustment_pesos ?? 0) > 0
                                ? `; additional PHP ${detail.price_adjustment_pesos} per purchased unit`
                                : ""}
                        </li>
                    );
                })}
            </ul>
        </section>
    );
}
