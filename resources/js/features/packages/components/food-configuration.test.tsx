import { cleanup, fireEvent, render, screen } from "@testing-library/react";
import { afterEach, describe, expect, it, vi } from "vitest";
import { FoodConfiguration } from "./food-configuration";
import { emptyFoodDetail } from "../menu-state";

afterEach(cleanup);

const base = {
    food: { id: 1, name: "Roast" },
    detail: emptyFoodDetail(),
    servingDefault: null,
    units: [
        { id: 1, name: "Kilogram", abbreviation: "kg", active: true },
        { id: 2, name: "Tray", abbreviation: "tray", active: true },
    ],
    fixed: true,
    bundle: false,
    packed: false,
    canPrice: true,
};

describe("Food Options", () => {
    it("keeps the ordinary food path collapsed and free of advanced fields", () => {
        render(<FoodConfiguration {...base} onChange={vi.fn()} />);
        expect(screen.getByText(/Standard serving/)).toBeVisible();
        const options = screen.getByText("Food Options");
        expect((options.parentElement as HTMLDetailsElement).open).toBe(false);
    });

    it("uses the dynamic unit catalog for a fixed portion", () => {
        const onChange = vi.fn();
        const { rerender } = render(
            <FoodConfiguration {...base} onChange={onChange} />,
        );
        fireEvent.click(screen.getByText("Food Options"));
        fireEvent.click(screen.getByLabelText("Set a fixed portion"));
        expect(onChange).toHaveBeenCalledWith(
            expect.objectContaining({ included_quantity: "1" }),
            undefined,
        );
        const fixed = {
            ...base.detail,
            included_quantity: "3",
            included_unit_id: "1",
        };
        rerender(
            <FoodConfiguration {...base} detail={fixed} onChange={onChange} />,
        );
        fireEvent.click(screen.getByText("Food Options"));
        fireEvent.change(screen.getByLabelText("Measurement Unit"), {
            target: { value: "2" },
        });
        expect(onChange).toHaveBeenLastCalledWith(
            expect.objectContaining({ included_unit_id: "2" }),
        );
    });

    it("requires confirmation before size choices replace a food charge", () => {
        const onChange = vi.fn();
        render(
            <FoodConfiguration
                {...base}
                detail={{ ...base.detail, price_adjustment_pesos: "50" }}
                onChange={onChange}
            />,
        );
        fireEvent.click(screen.getByText("Food Options"));
        fireEvent.click(screen.getByLabelText("Offer size choices"));
        expect(onChange).not.toHaveBeenCalled();
        fireEvent.click(
            screen.getByRole("button", { name: "Use Size Choices" }),
        );
        expect(onChange).toHaveBeenCalledWith(
            expect.objectContaining({ price_adjustment_pesos: "0" }),
            expect.objectContaining({ choices: expect.any(Array) }),
        );
    });

    it("hides the general food charge while size choices are active", () => {
        render(
            <FoodConfiguration
                {...base}
                onChange={vi.fn()}
                sizes={{
                    measurement_unit_id: 1,
                    choices: [
                        {
                            quantity: "3",
                            size_label: "Included",
                            extra_price_pesos: "0",
                            is_default: true,
                        },
                    ],
                }}
            />,
        );
        fireEvent.click(screen.getByText("Food Options"));
        expect(
            screen.queryByText("Charge extra when this food is selected"),
        ).not.toBeInTheDocument();
        expect(screen.getByText("Size choices")).toBeVisible();
    });
});
