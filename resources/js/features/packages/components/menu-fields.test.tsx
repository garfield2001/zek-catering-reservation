import { fireEvent, render, screen } from "@testing-library/react";
import { useState } from "react";
import { expect, it } from "vitest";
import { MenuFields } from "./menu-fields";
import { initialMenu, type MenuData } from "../menu-state";

const categories = [
    { id: 1, code: "chicken", name: "Chicken", active: true, sort_order: 1 },
    { id: 2, code: "beef", name: "Beef", active: true, sort_order: 2 },
];
const foods = [
    { id: 1, name: "Fried Chicken", category_id: 1 },
    { id: 2, name: "Garlic Chicken", category_id: 1 },
    { id: 3, name: "Beef Caldereta", category_id: 2 },
];

function ControlledMenu() {
    const [data, setData] = useState<MenuData>({
        ...initialMenu(),
        total_selection_count: 2,
    });

    return (
        <MenuFields
            data={data}
            onChange={(changes) =>
                setData((current) => ({ ...current, ...changes }))
            }
            foods={foods}
            categories={categories}
            main
            bundle={false}
            packed={false}
            canPrice
        />
    );
}

it("retains selected foods while the guided picker changes category and search", () => {
    render(<ControlledMenu />);
    fireEvent.click(screen.getByLabelText("Fried Chicken"));
    expect(screen.getByText(/Selected foods — 1 item/)).toBeVisible();

    const category = screen.getByRole("combobox", { name: "Category" });
    fireEvent.focus(category);
    fireEvent.change(category, { target: { value: "Beef" } });
    fireEvent.keyDown(category, { key: "Enter" });
    expect(screen.getByLabelText("Beef Caldereta")).toBeVisible();
    expect(screen.getByText("Fried Chicken")).toBeVisible();

    fireEvent.change(screen.getByLabelText("Search eligible foods"), {
        target: { value: "Caldereta" },
    });
    expect(screen.getByLabelText("Beef Caldereta")).toBeVisible();
    expect(screen.getByText(/Selected foods — 1 item/)).toBeVisible();
});
