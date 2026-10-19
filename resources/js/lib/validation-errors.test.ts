import { describe, expect, it } from "vitest";
import { validationErrors } from "./validation-errors";

describe("Laravel validation error mapping", () => {
    it("preserves dotted fields and joins backend messages", () => {
        expect(
            validationErrors({
                errors: {
                    "food_sizes.4.choices": [
                        "Choose a size.",
                        "Choose one default.",
                    ],
                    name: ["Required."],
                },
            }),
        ).toEqual({
            "food_sizes.4.choices": "Choose a size. Choose one default.",
            name: "Required.",
        });
    });
    it("accepts a single message and ignores malformed error values", () => {
        expect(
            validationErrors({
                errors: {
                    name: "Required.",
                    bad: { message: "wrong shape" },
                    mixed: [1, "wrong"],
                    empty: [],
                },
            }),
        ).toEqual({ name: "Required.", empty: "" });
    });
    it("handles absent or malformed response envelopes without throwing", () => {
        for (const input of [
            null,
            undefined,
            true,
            "html",
            [],
            {},
            { errors: null },
            { errors: [] },
            { errors: "invalid" },
        ])
            expect(validationErrors(input)).toEqual({});
    });
});
