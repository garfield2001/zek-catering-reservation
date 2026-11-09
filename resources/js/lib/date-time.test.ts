import { describe, expect, it } from "vitest";
import { formatManilaEvent } from "./date-time";

describe("formatManilaEvent", () => {
    it("formats stored Manila date and time without applying a UTC offset", () => {
        expect(formatManilaEvent("2026-11-05", "12:00:00")).toBe(
            "November 5, 2026 · 12:00 PM",
        );
        expect(formatManilaEvent("2026-11-05", "08:30:00")).toBe(
            "November 5, 2026 · 8:30 AM",
        );
    });
});
