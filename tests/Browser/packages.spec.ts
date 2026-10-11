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

test("Admin creates a package with its initial draft and enters pesos", async ({
    page,
}) => {
    await page.goto("/login");
    await page.getByLabel("Email address").fill(`admin-${run}@example.test`);
    await page.getByLabel("Password", { exact: true }).fill(password);
    await page.getByRole("button", { name: "Sign in" }).click();

    await page
        .getByRole("link", { name: "Add-on Services", exact: true })
        .click();
    await page.getByRole("link", { name: "Create package product" }).click();
    await page.getByLabel("Collection").selectOption({ label: "Pica-Pica" });
    await page.getByLabel("Product name").fill(`Browser bundle ${run}`);
    await page.getByRole("button", { name: "Create product" }).click();
    await expect(
        page.getByRole("heading", { name: /Standard.*draft Version 1/i }),
    ).toBeVisible();
    await page
        .getByRole("link", { name: "Edit price and quantity rules" })
        .click();
    await page.getByLabel("Base Price (PHP, per set)").fill("4500.00");
    await page.getByRole("button", { name: "Save draft" }).click();
    await expect(
        page.getByText("This is an editable draft", { exact: false }),
    ).toBeVisible();
    await expect(page.getByText("No selection groups")).toBeVisible();
});

test("Package draft explains the Food Item prerequisite before adding a group", async ({
    page,
}) => {
    await page.goto("/login");
    await page.getByLabel("Email address").fill(`admin-${run}@example.test`);
    await page.getByLabel("Password", { exact: true }).fill(password);
    await page.getByRole("button", { name: "Sign in" }).click();
    await page
        .getByRole("link", { name: "Add-on Services", exact: true })
        .click();
    await page
        .getByRole("link", { name: new RegExp(`Browser bundle ${run}`) })
        .click();
    await page.getByRole("link", { name: /Configure draft/ }).click();
    await page.getByRole("link", { name: "Add selection group" }).click();
    await expect(
        page.getByRole("heading", { name: /Add selection group/i }),
    ).toBeVisible();
    await expect(
        page.getByText(/Add an active, Collection-eligible Food Item/i),
    ).toBeVisible();
    await expect(
        page.getByRole("button", { name: "Add selection group" }),
    ).toBeDisabled();
});
