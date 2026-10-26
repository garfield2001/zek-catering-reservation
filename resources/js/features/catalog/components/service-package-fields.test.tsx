import { cleanup, fireEvent, render, screen } from "@testing-library/react";
import { afterEach, describe, expect, it, vi } from "vitest";
import { ServicePackageFields } from "./service-package-fields";

vi.mock("@inertiajs/react", () => ({
    usePage: () => ({
        props: {
            measurementUnits: [
                {
                    id: 7,
                    name: "Custom Basket",
                    abbreviation: "basket",
                    active: true,
                },
            ],
            canCreateUnit: false,
        },
    }),
}));
afterEach(cleanup);

describe("service package settings", () => {
    const value = {
        format: "separate_servings" as const,
        quantity: 1,
        measurement_unit_id: 7,
    };
    it("retains explicit container information when changing format", () => {
        const onChange = vi.fn();
        render(<ServicePackageFields value={value} onChange={onChange} />);
        fireEvent.change(screen.getByLabelText("How are foods provided?"), {
            target: { value: "combined_contents" },
        });
        expect(onChange).toHaveBeenCalledWith({
            ...value,
            format: "combined_contents",
        });
        expect(
            screen.getByRole("option", { name: "Custom Basket" }),
        ).toBeInTheDocument();
    });
    it("explains an established format lock with a safe next step", () => {
        render(
            <ServicePackageFields value={value} onChange={vi.fn()} locked />,
        );
        expect(screen.getByLabelText("How are foods provided?")).toBeDisabled();
        expect(
            screen.getByText(
                /Create another service for a different food format/,
            ),
        ).toBeVisible();
        expect(screen.getByLabelText("Quantity")).not.toBeDisabled();
    });
    it("describes complete purchased containers without multiplying by food count", () => {
        render(
            <ServicePackageFields
                value={{ ...value, format: "combined_contents" }}
                onChange={vi.fn()}
            />,
        );
        expect(
            screen.getByText(
                /Purchasing 10 units means 10 complete containers/,
            ),
        ).toBeVisible();
        expect(screen.getByLabelText("Quantity")).toHaveAttribute("max", "1");
    });
});
