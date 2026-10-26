import { describe, expect, it } from "vitest";
import {
    preparePackagePayload,
    type PackageFormData,
} from "./package-form-data";
import { emptyFoodDetail } from "./menu-state";

function data(): PackageFormData {
    return {
        name: "Configured package",
        description: "",
        active: true,
        family_id: 2,
        creation_token: "stable-request-id",
        version_id: 8,
        draft_revision: 3,
        base_price_pesos: "149.00",
        price_basis: "per_set",
        selection_policy: "uniform",
        minimum_quantity: "10",
        quantity_step: "1",
        maximum_quantity: "",
        estimated_capacity: "",
        total_selection_count: 2,
        food_item_ids: [1, 2],
        required_category_ids: [4],
        category_choice_rules: [
            {
                category_id: 4,
                minimum_count: 1,
                maximum_count: 1,
                maximum_count_explicit: true,
            },
        ],
        food_details: {
            1: {
                ...emptyFoodDetail(),
                is_fixed: true,
                included_quantity: "3",
                included_unit: "kg",
                price_adjustment_pesos: "20",
            },
        },
        food_sizes: {},
        serving_default: null,
        presentation: {
            format: "separate_servings",
            quantity: 1,
            measurement_unit_id: 2,
            food_overrides: {
                1: { quantity: 2, measurement_unit_id: 2 },
                99: { quantity: 1, measurement_unit_id: 2 },
            },
        },
    };
}

describe("package submission contract", () => {
    it("preserves commercial intent and filters deselected overrides without mutating state", () => {
        const input = data();
        const before = structuredClone(input);
        const payload = preparePackagePayload(input, {
            main: false,
            bundle: false,
            publish: false,
        });
        expect(payload.base_price_pesos).toBe("149.00");
        expect(payload.creation_token).toBe("stable-request-id");
        expect(payload.draft_revision).toBe(3);
        expect(payload.maximum_quantity).toBeNull();
        expect(payload.estimated_capacity).toBeNull();
        expect(Object.keys(payload.presentation!.food_overrides)).toEqual([
            "1",
        ]);
        expect(payload.food_details[1]).toMatchObject({
            included_quantity: "3",
            included_unit: "kg",
            price_adjustment_pesos: "20",
        });
        expect(payload.food_details[2]).toMatchObject({
            included_quantity: null,
            included_unit: null,
        });
        expect(payload.save_action).toBe("draft");
        expect(payload).not.toHaveProperty("confirm_publication");
        expect(input).toEqual(before);
    });
    it("retains fixed-bundle food totals and explicit publication confirmation", () => {
        const input = {
            ...data(),
            total_selection_count: 10,
            maximum_quantity: "100",
        };
        const payload = preparePackagePayload(input, {
            main: false,
            bundle: true,
            publish: true,
        });
        expect(payload.total_selection_count).toBe(2);
        expect(payload.required_category_ids).toEqual([]);
        expect(payload.maximum_quantity).toBe("100");
        expect(payload.save_action).toBe("publish");
        expect(payload.confirm_publication).toBe(true);
    });
    it("does not submit per-food measurements or premiums for Main Catering", () => {
        const input = {
            ...data(),
            presentation: null,
            price_basis: "per_pax" as const,
        };
        const payload = preparePackagePayload(input, {
            main: true,
            bundle: false,
            publish: false,
        });
        expect(payload.food_details).toEqual({
            1: { is_fixed: true },
            2: { is_fixed: false },
        });
        expect(payload.presentation).toBeNull();
        expect(payload.price_basis).toBe("per_pax");
        expect(payload.required_category_ids).toEqual([4]);
    });
});
