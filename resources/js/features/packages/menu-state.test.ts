import { describe, expect, it } from "vitest";
import { initialMenu, menuSummary } from "./menu-state";

const foods = [
    { id: 1, name: "Rice A", category_id: 1 },
    { id: 2, name: "Rice B", category_id: 1 },
    { id: 3, name: "Drink", category_id: 2 },
];
describe("menu feedback", () => {
    it("keeps Always Included foods separate from category customer choices", () => {
        const data = {
            ...initialMenu(),
            total_selection_count: 2,
            food_item_ids: [1, 2, 3],
            required_category_ids: [1],
            category_choice_rules: [
                {
                    category_id: 1,
                    minimum_count: 1,
                    maximum_count: 1,
                    maximum_count_explicit: true,
                },
            ],
            food_details: {
                3: {
                    is_fixed: true,
                    included_quantity: "",
                    included_unit: "",
                    price_adjustment_pesos: "0",
                },
            },
        };
        expect(menuSummary(data, foods).fixed).toHaveLength(1);
        expect(menuSummary(data, foods).impossible).toBe(false);
        expect(
            menuSummary({ ...data, food_item_ids: [3] }, foods).impossible,
        ).toBe(true);
    });
    it("flags unavailable choices and impossible fixed/category totals", () => {
        const data = {
            ...initialMenu(),
            total_selection_count: 1,
            food_item_ids: [1, 3],
            required_category_ids: [1, 2],
            category_choice_rules: [
                {
                    category_id: 1,
                    minimum_count: 1,
                    maximum_count: 1,
                    maximum_count_explicit: true,
                },
                {
                    category_id: 2,
                    minimum_count: 1,
                    maximum_count: 1,
                    maximum_count_explicit: true,
                },
            ],
        };
        expect(menuSummary(data, foods).impossible).toBe(true);
        expect(
            menuSummary(
                { ...data, total_selection_count: 2 },
                foods.slice(0, 2),
            ).impossible,
        ).toBe(true);
    });
    it("selecting all foods alone does not require categories or fix foods", () => {
        const result = menuSummary(
            { ...initialMenu(), food_item_ids: [1, 2, 3] },
            foods,
        );
        expect(result.fixed).toEqual([]);
        expect(result.requiredChoices).toBe(0);
    });
    it("shows the sole required category option as included before the server persists it", () => {
        const result = menuSummary(
            {
                ...initialMenu(),
                total_selection_count: 2,
                food_item_ids: [1, 3],
                required_category_ids: [1],
                category_choice_rules: [
                    {
                        category_id: 1,
                        minimum_count: 1,
                        maximum_count: 1,
                        maximum_count_explicit: true,
                    },
                ],
            },
            foods,
        );

        expect(result.fixed).toEqual([]);
        expect(result.effectiveFixed.map((food) => food.name)).toEqual([
            "Rice A",
        ]);
    });
});
