import { test, expect } from "@playwright/test";
import { randomBytes } from "node:crypto";
import { execFileSync } from "node:child_process";

const run = randomBytes(6).toString("hex");
const password = "Browser-" + randomBytes(18).toString("hex") + "A1";

test.beforeAll(() => {
    execFileSync("php", ["tests/Browser/seed.php"], {
        env: { ...process.env, BROWSER_RUN: run, BROWSER_PASSWORD: password },
    });
});

test("Admin creates a draft package variant without publishing it", async ({
    page,
}) => {
    await page.goto("/login");
    await page.getByLabel("Email address").fill(`admin-${run}@example.test`);
    await page.getByLabel("Password", { exact: true }).fill(password);
    await page.getByRole("button", { name: "Sign in" }).click();

    await page
        .getByRole("link", { name: "Package drafts", exact: true })
        .click();
    await page.getByRole("link", { name: "Create package product" }).click();
    await page.getByLabel("Collection").selectOption({ label: "Pica-Pica" });
    await page.getByLabel("Stable product code").fill(`browser_${run}`);
    await page.getByLabel("Product name").fill(`Browser bundle ${run}`);
    await page.getByRole("button", { name: "Create product" }).click();
    await expect(
        page.getByRole("heading", { name: `Browser bundle ${run}` }),
    ).toBeVisible();

    await page.getByRole("link", { name: "Add variant draft" }).click();
    await page.getByLabel("Variant code").fill("set_a");
    await page.getByLabel("Variant name").fill("Set A");
    await page.getByLabel("Base price in centavos (per set)").fill("450000");
    await page.getByRole("button", { name: "Create variant draft" }).click();
    await expect(
        page.getByText("This is an editable draft.", { exact: false }),
    ).toBeVisible();
    await expect(page.getByText("No selection slots")).toBeVisible();
});
