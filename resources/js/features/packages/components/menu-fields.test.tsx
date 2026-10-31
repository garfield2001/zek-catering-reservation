import {
    cleanup,
    fireEvent,
    render,
    screen,
    within,
} from "@testing-library/react";
import { useState } from "react";
import { afterEach, expect, it } from "vitest";
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

afterEach(cleanup);

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

function LegacyRangeMenu() {
    const [data, setData] = useState<MenuData>({
        ...initialMenu(),
        total_selection_count: 2,
        food_item_ids: [1, 2],
        required_category_ids: [1],
        category_choice_rules: [
            {
                category_id: 1,
                minimum_count: 1,
                maximum_count: 2,
                maximum_count_explicit: true,
            },
        ],
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
    const category = screen.getByRole("combobox", { name: "Category" });
    fireEvent.focus(category);
    fireEvent.change(category, { target: { value: "Chicken" } });
    fireEvent.keyDown(category, { key: "Enter" });
    fireEvent.click(screen.getByLabelText("Fried Chicken"));
    expect(screen.getByText(/Selected foods — 1 item/)).toBeVisible();

    fireEvent.focus(category);
    fireEvent.change(category, { target: { value: "Beef" } });
    fireEvent.keyDown(category, { key: "Enter" });
    expect(screen.getByLabelText("Beef Caldereta")).toBeVisible();
    expect(screen.getByText("Fried Chicken")).toBeVisible();

    fireEvent.change(screen.getByLabelText("Search this category"), {
        target: { value: "Caldereta" },
    });
    expect(screen.getByLabelText("Beef Caldereta")).toBeVisible();
    expect(screen.getByText(/Selected foods — 1 item/)).toBeVisible();
});

it("uses one exact customer-choice count and keeps always included foods separate", () => {
    render(<ControlledMenu />);
    const category = screen.getByRole("combobox", { name: "Category" });
    fireEvent.focus(category);
    fireEvent.change(category, { target: { value: "Chicken" } });
    fireEvent.keyDown(category, { key: "Enter" });
    fireEvent.click(screen.getByLabelText("Fried Chicken"));
    fireEvent.click(screen.getByLabelText("Garlic Chicken"));
    fireEvent.click(screen.getByLabelText("Always include Fried Chicken"));

    expect(
        screen.getByRole("region", { name: "Always included foods" }),
    ).toHaveTextContent("Fried Chicken");

    const chickenGroup = screen
        .getByRole("button", { name: "Require customer choice" })
        .closest("article");
    expect(chickenGroup).not.toBeNull();
    fireEvent.click(
        within(chickenGroup!).getByRole("button", {
            name: "Require customer choice",
        }),
    );

    const choice = screen.getByLabelText("Customer must choose");
    fireEvent.change(choice, { target: { value: "1" } });
    expect(choice).toHaveValue(1);
    expect(
        screen.getByText("Customers choose exactly 1 from Chicken."),
    ).toBeVisible();
    expect(screen.queryByLabelText("Minimum choices")).not.toBeInTheDocument();
    expect(screen.queryByLabelText("Maximum choices")).not.toBeInTheDocument();
});

it("preserves a legacy customer-choice range until an administrator explicitly replaces it", () => {
    render(<LegacyRangeMenu />);

    expect(screen.getByText("Legacy range preserved")).toBeVisible();
    expect(
        screen.getByText(/lets customers choose from 1 to 2 foods/),
    ).toBeVisible();
    expect(
        screen.queryByLabelText("Customer must choose"),
    ).not.toBeInTheDocument();

    fireEvent.change(
        screen.getByLabelText("Replace with an exact customer choice count"),
        { target: { value: "2" } },
    );

    expect(screen.getByLabelText("Customer must choose")).toHaveValue(2);
    expect(
        screen.getByText("Customers choose exactly 2 from Chicken."),
    ).toBeVisible();
});
