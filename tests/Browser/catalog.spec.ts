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

test("Admin can create a category, shared dish, offering and serving option", async ({
    page,
}) => {
    await page.goto("/login");
    await page.getByLabel("Email address").fill(`admin-${run}@example.test`);
    await page.getByLabel("Password", { exact: true }).fill(password);
    await page.getByRole("button", { name: "Sign in" }).click();

    await page
        .getByRole("link", { name: "Food categories", exact: true })
        .click();
    await page.getByRole("button", { name: "Add category" }).click();
    await page.getByLabel("Stable code").fill(`test_${run}`);
    await page.getByLabel("Category name").fill(`Test category ${run}`);
    await page.getByRole("button", { name: "Create category" }).click();
    await expect(
        page.getByRole("heading", { name: `Test category ${run}` }),
    ).toBeVisible();

    await page.getByRole("link", { name: "Food items", exact: true }).click();
    await page.getByRole("link", { name: "Add food item" }).click();
    await page.getByLabel("Stable food item code").fill(`dish_${run}`);
    await page.getByLabel("Food item name").fill(`Test dish ${run}`);
    await page
        .getByLabel("Food category")
        .selectOption({ label: `Test category ${run}` });
    await page.getByLabel("Main Catering Packages").check();
    await page.getByLabel("Food Tray Menu").check();
    await page.getByRole("button", { name: "Create food item" }).click();
    await expect(
        page.getByRole("heading", { name: `Test dish ${run}` }),
    ).toBeVisible();
    await expect(
        page.getByText("Main Catering Packages · Food Tray Menu"),
    ).toBeVisible();

    await page.getByRole("link", { name: "Add offering" }).click();
    await page
        .getByLabel("Collection")
        .selectOption({ label: "Food Tray Menu" });
    await page.getByLabel("Offering code").fill("classic");
    await page.getByLabel("Preparation name").fill("Classic tray");
    await page.getByRole("button", { name: "Create offering" }).click();
    await expect(
        page.getByRole("heading", { name: "Classic tray" }),
    ).toBeVisible();

    await page.getByRole("link", { name: "Add serving option" }).click();
    await page.getByLabel("Stable option code").fill("regular");
    await page.getByLabel("Serving label").fill("Regular tray");
    await page.getByLabel("Size per option").fill("1.500");
    await page.getByRole("button", { name: "Create serving option" }).click();
    await expect(page.getByText("Regular tray")).toBeVisible();
});

test("Admin can create a dynamic add-on collection from the modal", async ({
    page,
}) => {
    await page.goto("/login");
    await page.getByLabel("Email address").fill(`admin-${run}@example.test`);
    await page.getByLabel("Password", { exact: true }).fill(password);
    await page.getByRole("button", { name: "Sign in" }).click();
    await page.getByRole("link", { name: "Collections", exact: true }).click();
    await page.getByRole("button", { name: "Create collection" }).click();
    const dialog = page.getByRole("dialog", { name: "Create collection" });
    await dialog.getByLabel("Configuration type").selectOption("packed_meal");
    await dialog
        .getByLabel("Customer-facing name")
        .fill(`Dessert Boxes ${run}`);
    await dialog.getByRole("button", { name: "Create collection" }).click();
    await expect(
        page.getByRole("heading", { name: `Dessert Boxes ${run}` }),
    ).toBeVisible();
    await expect(
        page
            .locator("section")
            .filter({
                has: page.getByRole("heading", {
                    name: `Dessert Boxes ${run}`,
                }),
            })
            .getByText("Packed meals", { exact: true }),
    ).toBeVisible();
});
