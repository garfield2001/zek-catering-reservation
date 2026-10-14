import { describe, expect, it } from "vitest";
import { initialMenu, menuSummary } from "./menu-state";

const foods = [
    { id: 1, name: "Rice A", category_id: 1 },
    { id: 2, name: "Rice B", category_id: 1 },
    { id: 3, name: "Drink", category_id: 2 },
];
describe("menu feedback", () => {
    it("counts automatic and explicit fixed intent once and keeps categories at least one", () => {
        const data = {
            ...initialMenu(),
            total_selection_count: 2,
            food_item_ids: [1, 3],
            required_category_ids: [1, 2],
            food_details: {
                1: {
                    is_fixed: true,
                    included_quantity: "",
                    included_unit: "",
                    price_adjustment_pesos: "0",
                },
            },
        };
        expect(menuSummary(data, foods).fixed).toHaveLength(2);
        expect(menuSummary(data, foods).impossible).toBe(false);
        expect(
            menuSummary({ ...data, food_item_ids: [1, 2, 3] }, foods).fixed,
        ).toHaveLength(2);
    });
    it("flags unavailable choices and impossible fixed/category totals", () => {
        const data = {
            ...initialMenu(),
            total_selection_count: 1,
            food_item_ids: [1, 3],
            required_category_ids: [1, 2],
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
});
