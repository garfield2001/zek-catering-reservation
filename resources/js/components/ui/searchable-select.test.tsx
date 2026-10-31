import { cleanup, fireEvent, render, screen } from "@testing-library/react";
import { afterEach, expect, it, vi } from "vitest";
import { SearchableSelect } from "./searchable-select";

afterEach(cleanup);

it("filters a bounded list and selects an option with the keyboard", () => {
    const change = vi.fn();
    render(
        <label>
            Category
            <SearchableSelect
                value=""
                onChange={change}
                placeholder="Choose a category"
                options={Array.from({ length: 8 }, (_, index) => ({
                    value: index + 1,
                    label: `Category ${index + 1}`,
                }))}
            />
        </label>,
    );

    const picker = screen.getByRole("combobox", { name: "Category" });
    fireEvent.focus(picker);
    expect(screen.getByRole("listbox").className).toContain("max-h-[11.25rem]");
    fireEvent.change(picker, { target: { value: "7" } });
    fireEvent.keyDown(picker, { key: "Enter" });
    expect(change).toHaveBeenCalledWith("7");
});

it("closes immediately after a mouse choice and supports Escape", () => {
    render(
        <label>
            Category
            <SearchableSelect
                value=""
                onChange={vi.fn()}
                placeholder="Choose a category"
                options={[
                    { value: "1", label: "Rice" },
                    { value: "2", label: "Drinks" },
                ]}
            />
        </label>,
    );

    const picker = screen.getByRole("combobox", { name: "Category" });
    fireEvent.click(picker);
    fireEvent.click(screen.getByRole("option", { name: "Rice" }));
    expect(screen.queryByRole("listbox")).not.toBeInTheDocument();

    fireEvent.click(picker);
    fireEvent.keyDown(picker, { key: "Escape" });
    expect(screen.queryByRole("listbox")).not.toBeInTheDocument();
});

it("starts a new search instead of appending to the selected label", () => {
    render(
        <label>
            Category
            <SearchableSelect
                value="1"
                onChange={vi.fn()}
                placeholder="Choose a category"
                options={[{ value: "1", label: "Belly (1)" }]}
            />
        </label>,
    );

    const picker = screen.getByRole("combobox", { name: "Category" });
    expect(picker).toHaveValue("Belly (1)");

    fireEvent.click(picker);

    expect(picker).toHaveValue("");
});
