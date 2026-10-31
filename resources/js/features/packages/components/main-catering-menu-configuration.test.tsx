import { cleanup, fireEvent, render, screen } from "@testing-library/react";
import { useState } from "react";
import { afterEach, expect, it, vi } from "vitest";
import { initialMenu, type MenuData } from "../menu-state";
import { MainCateringMenuConfiguration } from "./main-catering-menu-configuration";

const categories = [
    { id: 1, code: "rice", name: "Rice", active: true, sort_order: 1 },
    { id: 2, code: "drinks", name: "Drinks", active: true, sort_order: 2 },
];
const foods = Array.from({ length: 9 }, (_, index) => ({
    id: index + 1,
    name: `Rice ${index + 1}`,
    category_id: 1,
})).concat([
    { id: 10, name: "Cola", category_id: 2 },
    { id: 11, name: "Juice", category_id: 2 },
]);

afterEach(cleanup);

function ControlledMenu() {
    const [data, setData] = useState<MenuData>({
        ...initialMenu(),
        total_selection_count: 2,
        food_item_ids: [10],
    });
    return (
        <MainCateringMenuConfiguration
            data={data}
            foods={foods}
            categories={categories}
            errors={{}}
            onChange={(changes) =>
                setData((current) => ({ ...current, ...changes }))
            }
            onAddFood={vi.fn()}
        />
    );
}

it("starts category-first, paginates within a category, and retains selections", () => {
    render(<ControlledMenu />);
    expect(
        screen.getByText(
            /Choose a Food Category to browse eligible Food Items/,
        ),
    ).toBeVisible();
    expect(screen.queryByLabelText("Rice 1")).not.toBeInTheDocument();
    expect(screen.getByText("Cola")).toBeVisible();

    const category = screen.getByRole("combobox", { name: "Category" });
    fireEvent.focus(category);
    fireEvent.change(category, { target: { value: "Rice" } });
    fireEvent.keyDown(category, { key: "Enter" });
    expect(screen.getByLabelText("Rice 1")).toBeVisible();
    expect(screen.queryByLabelText("Rice 9")).not.toBeInTheDocument();
    expect(screen.getByText(/Page 1 of 2/)).toBeVisible();

    fireEvent.click(screen.getByRole("button", { name: "Next" }));
    expect(screen.getByLabelText("Rice 9")).toBeVisible();
    expect(screen.getByText("Cola")).toBeVisible();
});

it("keeps exact customer choices separate from individual always-included foods", () => {
    render(<ControlledMenu />);
    const category = screen.getByRole("combobox", { name: "Category" });
    fireEvent.focus(category);
    fireEvent.change(category, { target: { value: "Drinks" } });
    fireEvent.keyDown(category, { key: "Enter" });
    fireEvent.click(screen.getByLabelText("Juice"));
    fireEvent.click(screen.getByRole("button", { name: "Configure Drinks" }));
    fireEvent.click(screen.getByLabelText("Always include Cola"));
    fireEvent.click(screen.getByLabelText("Require customer choice"));

    expect(screen.getByLabelText("Customer must choose")).toHaveValue(1);
    expect(screen.getByLabelText("Always include Cola")).toBeChecked();
    fireEvent.click(screen.getByRole("button", { name: "Close dialog" }));
    expect(
        screen.getByRole("complementary", { name: "Menu summary" }),
    ).toHaveTextContent("Drinks: exactly 1");
});

it("opens category configuration from its chip without losing selections", () => {
    render(<ControlledMenu />);

    fireEvent.click(screen.getByRole("button", { name: "Drinks (1)" }));

    expect(
        screen.getByRole("dialog", { name: "Configure Drinks Foods" }),
    ).toBeVisible();
    expect(
        screen.getByRole("dialog", { name: "Configure Drinks Foods" })
            .textContent,
    ).toContain("Cola");
    fireEvent.click(screen.getByRole("button", { name: "Remove" }));
    expect(
        screen.getByText("Select Food Items above to configure this package."),
    ).toBeVisible();
});
