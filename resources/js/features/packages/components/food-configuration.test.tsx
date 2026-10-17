import { cleanup, render, screen, fireEvent } from "@testing-library/react";
import { afterEach, describe, expect, it, vi } from "vitest";
import { FoodConfiguration } from "./food-configuration";
import { emptyFoodDetail } from "../menu-state";

afterEach(cleanup);

const base = {
    food: { id: 1, name: "Roast" },
    detail: emptyFoodDetail(),
    servingDefault: {
        quantity: "1",
        measurement_unit_id: 1,
        size_label: "Medium",
    },
    units: [{ id: 1, name: "Tray", abbreviation: "tray", active: true }],
    fixed: true,
    bundle: false,
    packed: false,
    canPrice: true,
};
describe("contextual food configuration", () => {
    it("shows inheritance and keeps existing premium pricing when returning a measurement to the default", () => {
        const onChange = vi.fn();
        const detail = {
            ...emptyFoodDetail(),
            included_quantity: "3",
            included_unit: "kg",
            price_adjustment_pesos: "25",
        };
        render(
            <FoodConfiguration {...base} detail={detail} onChange={onChange} />,
        );
        expect(screen.getByText(/Fixed inclusion · Custom/)).toHaveTextContent(
            "3 kg",
        );
        fireEvent.click(
            screen.getByRole("button", {
                name: "Return Roast to package default",
            }),
        );
        expect(onChange).toHaveBeenCalledWith(
            { ...detail, included_quantity: "", included_unit: "" },
            undefined,
        );
    });
    it("hides duplicate measurement and general price inputs when size choices are enabled", () => {
        render(
            <FoodConfiguration
                {...base}
                onChange={vi.fn()}
                sizes={{
                    measurement_unit_id: 1,
                    choices: [
                        {
                            quantity: "1",
                            size_label: "Small",
                            extra_price_pesos: "0",
                            is_default: true,
                        },
                    ],
                }}
            />,
        );
        expect(
            screen.queryByLabelText("Included quantity of Roast (optional)"),
        ).not.toBeInTheDocument();
        expect(
            screen.queryByLabelText("Extra price for Roast (PHP)"),
        ).not.toBeInTheDocument();
        expect(screen.getByText(/Fixed inclusion · Custom/)).toHaveTextContent(
            "Small tray",
        );
    });
    it("requires explicit confirmation before replacing a separate food premium with size pricing", () => {
        const onChange = vi.fn();
        render(
            <FoodConfiguration
                {...base}
                detail={{ ...base.detail, price_adjustment_pesos: "50" }}
                onChange={onChange}
            />,
        );
        fireEvent.click(screen.getByLabelText("Offer Size Choices for Roast"));
        expect(onChange).not.toHaveBeenCalled();
        fireEvent.click(
            screen.getByRole("button", { name: "Use Size Choices" }),
        );
        expect(onChange).toHaveBeenCalledWith(
            expect.objectContaining({ price_adjustment_pesos: "0" }),
            expect.objectContaining({ choices: expect.any(Array) }),
        );
    });
    it("prevents Staff without pricing permission from removing existing size prices", () => {
        render(
            <FoodConfiguration
                {...base}
                canPrice={false}
                onChange={vi.fn()}
                sizes={{
                    measurement_unit_id: 1,
                    choices: [
                        {
                            quantity: "1",
                            extra_price_pesos: "0",
                            is_default: true,
                        },
                    ],
                }}
            />,
        );
        expect(
            screen.getByRole("button", {
                name: "Return Roast to package default",
            }),
        ).toBeDisabled();
        expect(screen.getByText(/pricing permission to remove/)).toBeVisible();
    });
});
