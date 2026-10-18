import { afterEach, describe, expect, it, vi } from "vitest";
import { cleanup, fireEvent, render, screen } from "@testing-library/react";
import {
    PackagePresentationFields,
    purchasedUnit,
    separatePresentation,
} from "./package-presentation-fields";

afterEach(cleanup);
const units = [{ id: 1, name: "Meal Box", abbreviation: "box", active: true }];
describe("package presentation", () => {
    it("changes format without silently deleting existing overrides or serving facts", () => {
        const onChange = vi.fn();
        const value = {
            ...separatePresentation(),
            quantity: 2,
            measurement_unit_id: 1,
            food_overrides: { 5: { quantity: 3, measurement_unit_id: 1 } },
        };
        render(
            <PackagePresentationFields
                value={value}
                units={units}
                onChange={onChange}
                legacySplit={false}
            />,
        );
        fireEvent.change(
            screen.getByLabelText(/What does the customer receive/),
            { target: { value: "combined_contents" } },
        );
        expect(onChange).toHaveBeenCalledWith({
            ...value,
            format: "combined_contents",
        });
    });
    it("uses configured presentation names rather than a family name or fixed pack label", () => {
        expect(
            purchasedUnit(
                {
                    ...separatePresentation(),
                    format: "combined_contents",
                    quantity: 1,
                    measurement_unit_id: 1,
                },
                units,
            ),
        ).toBe("complete Meal Box");
        expect(purchasedUnit(separatePresentation(), units)).toBe(
            "complete package set",
        );
    });
    it("discloses unknown legacy format and preserves legacy split configuration", () => {
        const { rerender } = render(
            <PackagePresentationFields
                value={null}
                units={units}
                onChange={vi.fn()}
                legacySplit={false}
            />,
        );
        expect(
            screen.getByRole("option", { name: /Legacy format not recorded/ }),
        ).toBeTruthy();
        rerender(
            <PackagePresentationFields
                value={null}
                units={units}
                onChange={vi.fn()}
                legacySplit
            />,
        );
        expect(screen.queryByRole("combobox")).toBeNull();
        expect(
            screen.getByText(/legacy package allows different meal groups/),
        ).toBeTruthy();
    });
});
