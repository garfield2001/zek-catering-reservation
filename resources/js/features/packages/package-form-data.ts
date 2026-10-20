import { emptyFoodDetail, type MenuData } from "./menu-state";
import type { FoodSizes } from "./components/food-size-fields";
import type { ServingDefault } from "./components/serving-default-fields";
import type { PackagePresentationInput } from "./components/package-presentation-fields";

export type PackageFormData = MenuData & {
    food_sizes: FoodSizes;
    serving_default: ServingDefault | null;
    presentation: PackagePresentationInput | null;
    name: string;
    description: string;
    active: boolean;
    family_id: number;
    creation_token?: string;
    version_id?: number;
    draft_revision?: number;
    base_price_pesos: string;
    price_basis: "per_pax" | "per_pack" | "per_set";
    selection_policy: "uniform" | "split";
    minimum_quantity: string;
    quantity_step: string;
    maximum_quantity: string;
    estimated_capacity: string;
};

/** Serialize existing editor intent only; validation and pricing remain authoritative in Laravel. */
export function preparePackagePayload(
    data: PackageFormData,
    {
        main,
        bundle,
        publish,
    }: { main: boolean; bundle: boolean; publish: boolean },
) {
    return {
        ...data,
        presentation: data.presentation
            ? {
                  ...data.presentation,
                  food_overrides: Object.fromEntries(
                      Object.entries(data.presentation.food_overrides).filter(
                          ([id]) => data.food_item_ids.includes(Number(id)),
                      ),
                  ),
              }
            : null,
        save_action: publish ? "publish" : "draft",
        ...(publish ? { confirm_publication: true } : {}),
        food_sizes: Object.fromEntries(
            Object.entries(data.food_sizes).filter(([id]) =>
                data.food_item_ids.includes(Number(id)),
            ),
        ),
        total_selection_count: bundle
            ? data.food_item_ids.length
            : data.total_selection_count,
        required_category_ids: bundle ? [] : data.required_category_ids,
        maximum_quantity: data.maximum_quantity || null,
        estimated_capacity: data.estimated_capacity || null,
        food_details: Object.fromEntries(
            data.food_item_ids.map((id) => {
                const detail = data.food_details[id] ?? emptyFoodDetail();
                return [
                    id,
                    main
                        ? { is_fixed: detail.is_fixed }
                        : {
                              ...detail,
                              included_quantity:
                                  detail.included_quantity || null,
                              included_unit: detail.included_unit || null,
                              included_unit_id: detail.included_unit_id || null,
                          },
                ];
            }),
        ),
    };
}
