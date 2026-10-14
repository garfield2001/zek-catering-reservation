import { describe, expect, it } from "vitest";
import { configurationDiff } from "./configuration-diff";
import { formatPesos, minorToPesoInput } from "./money";
import type { PublishedPackageSnapshot } from "./types";

const snapshot: PublishedPackageSnapshot = {
    schema: 2,
    collection: {
        name: "Catering",
        description: null,
        behavior_type: "main_package",
    },
    product: { name: "Package A", description: null },
    package: { name: "Package A", description: null },
    version: {
        number: 1,
        base_price_minor: "25050",
        price_basis: "per_pax",
        minimum_quantity: 150,
        quantity_step: 1,
        maximum_quantity: null,
        selection_policy: "uniform",
    },
    groups: [
        {
            group_code: "choice",
            name: "Customer Choices",
            mode: "select_one",
            minimum_count: 1,
            maximum_count: 1,
            inclusion_count: "1.000",
            options: [
                {
                    offering_option_id: 1,
                    is_default: true,
                    price_adjustment_minor: "0",
                    food_item: { name: "Chicken", description: null },
                    food_category: { name: "Poultry" },
                    serving_option: {
                        label: "Tray",
                        unit: "tray",
                        quantity: "1.000",
                    },
                },
            ],
            category_rules: [],
            food_item_rules: [],
        },
    ],
};

describe("package money and publication differences", () => {
    it("formats centavos as Philippine pesos without floating point", () => {
        expect(minorToPesoInput("25050")).toBe("250.50");
        expect(formatPesos("25050")).toBe("₱250.50");
    });

    it("lists price and choice changes", () => {
        const next = structuredClone(snapshot);
        next.version.base_price_minor = "30000";
        next.groups[0].options[0].price_adjustment_minor = "250";
        expect(configurationDiff(snapshot, next)).toEqual([
            "Base Price: ₱250.50 → ₱300.00",
            "Changed Chicken adjustment in Customer Choices: ₱0.00 → ₱2.50",
        ]);
    });
});
