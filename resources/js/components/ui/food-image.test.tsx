import { render, screen, fireEvent, cleanup } from "@testing-library/react";
import { describe, it, expect, afterEach } from "vitest";
import { FoodImage } from "./food-image";

afterEach(cleanup);

describe("FoodImage", () => {
    it("shows an honest placeholder without requiring an upload", () => {
        render(<FoodImage name="Rice" />);
        expect(screen.getByText("No image for Rice")).toBeInTheDocument();
        expect(screen.queryByRole("img")).not.toBeInTheDocument();
    });

    it("falls back on failure and permits a replacement image to load", () => {
        const { rerender } = render(
            <FoodImage name="Rice" src="/missing.png" />,
        );
        fireEvent.error(screen.getByRole("img"));
        expect(screen.getByText("No image for Rice")).toBeInTheDocument();
        rerender(<FoodImage name="Rice" src="/replacement.png" />);
        fireEvent.load(screen.getByRole("img"));
        expect(screen.getByRole("img")).not.toHaveClass("opacity-0");
        expect(screen.queryByText("No image for Rice")).not.toBeInTheDocument();
    });
});
