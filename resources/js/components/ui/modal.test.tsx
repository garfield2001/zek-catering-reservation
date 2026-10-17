import { cleanup, fireEvent, render, screen } from "@testing-library/react";
import { afterEach, expect, it, vi } from "vitest";
import { Modal } from "./modal";
import { useDialogForm } from "./dialog-form-guard";

afterEach(cleanup);

function Form({ dirty = false, busy = false }) {
    useDialogForm(dirty, busy);
    return <input aria-label="Food name" defaultValue="Saved food" />;
}

it("closes an unchanged dialog without a discard prompt", () => {
    const close = vi.fn();
    render(
        <Modal open onClose={close} title="Edit Food">
            <Form />
        </Modal>,
    );
    fireEvent.click(screen.getByRole("button", { name: "Close dialog" }));
    expect(close).toHaveBeenCalledOnce();
    expect(screen.queryByRole("alertdialog")).not.toBeInTheDocument();
});

it("requires explicit discard and lets the user keep editing", () => {
    const close = vi.fn();
    render(
        <Modal open onClose={close} title="Edit Food">
            <Form dirty />
        </Modal>,
    );
    fireEvent.click(screen.getByRole("button", { name: "Close dialog" }));
    expect(close).not.toHaveBeenCalled();
    expect(screen.getByRole("alertdialog")).toBeInTheDocument();
    fireEvent.click(screen.getByRole("button", { name: "Keep Editing" }));
    expect(screen.getByLabelText("Food name")).toHaveValue("Saved food");
    expect(close).not.toHaveBeenCalled();
    fireEvent.click(screen.getByRole("button", { name: "Close dialog" }));
    fireEvent.click(screen.getByRole("button", { name: "Discard Changes" }));
    expect(close).toHaveBeenCalledOnce();
});

it("blocks dismissal while a form save is in progress", () => {
    const close = vi.fn();
    render(
        <Modal open onClose={close} title="Edit Food">
            <Form dirty busy />
        </Modal>,
    );
    fireEvent.click(screen.getByRole("button", { name: "Close dialog" }));
    fireEvent.keyDown(screen.getByRole("dialog"), { key: "Escape" });
    expect(close).not.toHaveBeenCalled();
    expect(screen.queryByRole("alertdialog")).not.toBeInTheDocument();
});

it("uses the same discard protection for Escape", () => {
    const close = vi.fn();
    render(
        <Modal open onClose={close} title="Edit Food">
            <Form dirty />
        </Modal>,
    );
    fireEvent.keyDown(screen.getByRole("dialog"), { key: "Escape" });
    expect(screen.getByRole("alertdialog")).toBeInTheDocument();
    expect(close).not.toHaveBeenCalled();
});
