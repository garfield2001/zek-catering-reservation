import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { afterEach, expect, test, vi } from "vitest";
import type { VariantVersion, PackageProduct, PackageVariant } from "../types";
import { PreviewBuilder } from "./preview-builder";

afterEach(() => vi.unstubAllGlobals());

test("sends selections to Laravel and renders only the server-calculated amount", async () => {
    const fetchMock = vi.fn().mockResolvedValue({
        ok: true,
        json: async () => ({
            total_minor: "12344",
            effective_quantity_rules: {
                minimum_quantity: 1,
                quantity_step: 1,
                maximum_quantity: null,
            },
            groups: [
                {
                    quantity: 2,
                    unit_base_minor: "5000",
                    base_subtotal_minor: "10000",
                    adjustments: [
                        {
                            group_code: "drink",
                            offering_option_id: 9,
                            unit_amount_minor: "1172",
                            line_amount_minor: "2344",
                        },
                    ],
                    line_total_minor: "12344",
                },
            ],
        }),
    });
    vi.stubGlobal("fetch", fetchMock);
    const product = {
        id: 1,
        family_id: 1,
        code: "meal",
        name: "Meal",
        description: null,
        sort_order: 0,
        active: true,
    } satisfies PackageProduct;
    const variant = {
        id: 2,
        product_id: 1,
        code: "a",
        name: "A",
        description: null,
        sort_order: 0,
        active: true,
    } satisfies PackageVariant;
    const version = {
        id: 3,
        variant_id: 2,
        family_id: 1,
        version_number: 1,
        status: "draft",
        draft_revision: 1,
        base_price_minor: "5000",
        price_basis: "per_pack",
        selection_policy: "uniform",
        minimum_override: null,
        step_override: null,
        maximum_override: null,
        maximum_override_enabled: false,
        minimum_quantity: 2,
        quantity_step: 1,
        maximum_quantity: null,
        groups: [
            {
                id: 4,
                version_id: 3,
                group_code: "drink",
                name: "Drink",
                mode: "select_one",
                slot_kind: "drink",
                minimum_count: 1,
                maximum_count: 1,
                unique_dishes: true,
                category_mode: "free",
                inclusion_count: "1.000",
                sort_order: 0,
                options: [
                    {
                        id: 5,
                        group_id: 4,
                        offering_option_id: 9,
                        is_default: true,
                        price_adjustment_minor: "1172",
                        offering_option: {
                            id: 9,
                            label: "Cola",
                            unit: "bottle",
                            quantity: "1.000",
                        },
                    },
                ],
            },
        ],
    } satisfies VariantVersion;
    render(
        <PreviewBuilder
            product={product}
            variant={variant}
            version={version}
        />,
    );
    fireEvent.click(
        screen.getByRole("button", { name: "Calculate on server" }),
    );
    await waitFor(() =>
        expect(
            screen.getByText(/Draft preview: ₱123\.44 total/),
        ).toBeInTheDocument(),
    );
    expect(fetchMock).toHaveBeenCalledTimes(1);
    const body = JSON.parse(fetchMock.mock.calls[0][1].body as string);
    expect(body).toEqual({
        quantity: 2,
        groups: [{ quantity: 2, selections: { drink: [9] } }],
    });
    expect(body).not.toHaveProperty("total_minor");
});
