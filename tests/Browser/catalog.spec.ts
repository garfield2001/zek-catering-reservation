import { test, expect } from "@playwright/test";
import { randomBytes } from "node:crypto";
import { execFileSync } from "node:child_process";
import {
    login,
    addCategory,
    addFood,
    createPackage,
    step,
    inlineFood,
    savePackage,
} from "./workflow";
const run = randomBytes(6).toString("hex");
const password = "Browser-" + randomBytes(18).toString("hex") + "A1";
test.beforeAll(() => {
    execFileSync("php", ["tests/Browser/seed.php"], {
        env: {
            ...process.env,
            BROWSER_RUN: run,
            BROWSER_PASSWORD: password,
            BROWSER_LEGACY: "0",
        },
    });
});

test("unified catalog manages categories, globally available foods and measurement units", async ({
    page,
}) => {
    await login(page, run, password);
    await page.goto("/admin/add-on-services");
    await page.getByRole("button", { name: "Create Add-on Service" }).click();
    await page.getByLabel("Service name").fill("Tray Market");
    await page.getByRole("radio", { name: /Sold Individually/ }).check();
    await page
        .getByRole("button", { name: "Create Service", exact: true })
        .click();
    await page
        .getByRole("link", { name: "Open newly created service" })
        .click();
    await expect(
        page.getByRole("button", { name: "Create Item" }),
    ).toBeVisible();
    await page.goto("/admin/food-catalog");
    await addCategory(page, "Other Foods");
    await page.getByText("Catalog Settings", { exact: true }).click();
    await page
        .getByRole("button", { name: "Edit category Other Foods" })
        .click();
    await page
        .getByRole("dialog")
        .getByLabel("Category name")
        .fill("Party Foods");
    await page
        .getByRole("dialog")
        .getByRole("button", { name: "Save category" })
        .click();
    await expect(page.getByRole("dialog")).not.toBeVisible();
    await addFood(page, "Spring Rolls", "Party Foods", "Tray Market");
    await page.getByRole("button", { name: "Edit food Spring Rolls" }).click();
    await page
        .getByRole("dialog")
        .getByLabel("Food item name")
        .fill("Crispy Spring Rolls");
    await page
        .getByRole("dialog")
        .getByRole("button", { name: "Save food item" })
        .click();
    await expect(
        page.getByRole("heading", { name: "Crispy Spring Rolls", exact: true }),
    ).toBeVisible();
    await page.getByLabel("Search foods or categories").fill("Crispy");
    await expect(
        page.getByRole("heading", { name: "Crispy Spring Rolls" }),
    ).toBeVisible();
    await page.screenshot({
        path: "test-results/unified-food-catalog.png",
        fullPage: true,
    });
});

test("dynamic packed service opens its workspace and creates a service-scoped package", async ({
    page,
}) => {
    await login(page, run, password);
    await page.goto("/admin/add-on-services");
    await page.getByRole("button", { name: "Create Add-on Service" }).click();
    await page.getByLabel("Service name").fill("Lunch Boxes");
    await page.getByRole("radio", { name: /Sold by Package/ }).check();
    await page
        .getByRole("button", { name: "Create Service", exact: true })
        .click();
    await page
        .getByRole("link", { name: "Open newly created service" })
        .click();
    await expect(
        page.getByRole("heading", { name: "Lunch Boxes", exact: true }),
    ).toBeVisible();
    await createPackage(page, "Package A");
    await inlineFood(page, "Packed Rice", "Pack Foods");
    await inlineFood(page, "Packed Chicken", "Pack Foods");
    await page.getByLabel("Total included food selections").fill("2");
    await page.getByLabel("Packed Rice", { exact: true }).check();
    await page.getByLabel("Packed Chicken", { exact: true }).check();
    await step(page, "Pricing & Quantity");
    await page
        .getByText("Packed meal settings (optional)", { exact: true })
        .click();
    await page.getByLabel("Sell each purchased unit as a packed meal").check();
    await page.getByLabel("Price per pack (PHP)").fill("125.50");
    await page.getByLabel("Minimum packs").fill("10");
    await page.getByLabel("Meal selections").selectOption("split");
    await savePackage(page);
    await page
        .getByRole("button", { name: "Edit Package", exact: true })
        .click();
    await step(page, "Pricing & Quantity");
    await expect(page.getByLabel("Price per pack (PHP)")).toHaveValue("125.50");
    await expect(page.getByLabel("Minimum packs")).toHaveValue("10");
    await expect(page.getByLabel("Meal selections")).toHaveValue("split");
    await page.screenshot({
        path: "test-results/packed-service-package.png",
        fullPage: true,
    });
});

test("Bellychon configurable package uses shared choices, optional kilograms and capacity", async ({
    page,
}) => {
    test.setTimeout(120000);
    await login(page, run, password);
    await page.goto("/admin/add-on-services");
    await page.getByRole("button", { name: "Create Add-on Service" }).click();
    await page.getByLabel("Service name").fill("Bellychon Package");
    await page.getByRole("radio", { name: /Sold by Package/ }).check();
    await page
        .getByRole("button", { name: "Create Service", exact: true })
        .click();
    await page
        .getByRole("link", { name: "Open newly created service" })
        .click();
    await createPackage(page, "Package A");
    await inlineFood(page, "Lechon Belly", "Belly");
    await inlineFood(page, "Roast Chicken", "Chicken");
    await inlineFood(page, "Chicken BBQ", "Chicken");
    await page.getByLabel("Total included food selections").fill("2");
    for (const food of ["Lechon Belly", "Roast Chicken", "Chicken BBQ"])
        await page.getByLabel(food, { exact: true }).check();
    await page
        .getByRole("group", { name: /^Belly / })
        .getByLabel("Require at least one")
        .check();
    await page
        .getByRole("group", { name: /^Chicken / })
        .getByLabel("Require at least one")
        .check();
    await page
        .getByText("Quantity & preparation details for Lechon Belly", {
            exact: true,
        })
        .click();
    await page
        .getByLabel("Included quantity of Lechon Belly (optional)")
        .fill("3");
    await page.getByLabel("Measurement for Lechon Belly").selectOption("kg");
    await expect(page.getByLabel("Menu summary")).toContainText(
        "Fixed inclusions: Lechon Belly",
    );
    await step(page, "Pricing & Quantity");
    await page.getByLabel("Price per package (PHP)").fill("4500.00");
    await page.getByLabel("Minimum packages").fill("1");
    await page
        .getByLabel("Estimated people served per package (optional)")
        .fill("15");
    await step(page, "Review & Publish");
    await page
        .getByRole("link", { name: "Review & Publish", exact: true })
        .click();
    await expect(page.getByText(/3.000 kg per purchased unit/)).toBeVisible();
    await expect(page.getByText(/Estimated capacity: 15/)).toBeVisible();
    await page.screenshot({
        path: "test-results/configurable-addon-review.png",
        fullPage: true,
    });
    await page.getByLabel(/I reviewed this configuration/).check();
    await page
        .getByRole("button", { name: "Publish Package", exact: true })
        .click();
    await page
        .getByRole("alertdialog")
        .getByRole("button", { name: "Confirm Publication" })
        .click();
    await expect(
        page.getByText("The current publication is available.", {
            exact: true,
        }),
    ).toBeVisible();
    await page.reload();
    await expect(
        page.getByText("The current publication is available.", {
            exact: true,
        }),
    ).toBeVisible();
    await expect(
        page.getByText(/Selection Group|Slot kind|Inclusion type|Variant/),
    ).toHaveCount(0);
});

test("fixed bundles use only included foods without customer-choice controls", async ({
    page,
}) => {
    await login(page, run, password);
    await page.goto("/admin/add-on-services");
    await page.getByRole("button", { name: "Create Add-on Service" }).click();
    await page.getByLabel("Service name").fill("Tea Sets");
    await page.getByRole("radio", { name: /Sold by Package/ }).check();
    await page
        .getByRole("button", { name: "Create Service", exact: true })
        .click();
    await page
        .getByRole("link", { name: "Open newly created service" })
        .click();
    await createPackage(page, "Afternoon Set");
    await inlineFood(page, "Cheese Sandwich", "Snacks");
    await page.getByLabel("Cheese Sandwich", { exact: true }).check();
    await page
        .getByLabel("Always include Cheese Sandwich", { exact: true })
        .check();
    await step(page, "Pricing & Quantity");
    await page.getByLabel("Price per package (PHP)").fill("120.00");
    await step(page, "Review & Publish");
    await page
        .getByRole("link", { name: "Review & Publish", exact: true })
        .click();
    await page.getByLabel(/I reviewed this configuration/).check();
    await page
        .getByRole("button", { name: "Publish Package", exact: true })
        .click();
    await page
        .getByRole("alertdialog")
        .getByRole("button", { name: "Confirm Publication" })
        .click();
    await expect(
        page.getByText("The current publication is available.", {
            exact: true,
        }),
    ).toBeVisible();
});

test("copied package quantities remain independently editable", async ({
    page,
}) => {
    await login(page, run, password);
    await page.goto("/admin/catering-packages");
    await createPackage(page, "Quantity Source");
    await inlineFood(page, "Quantity Food", "Quantity Foods");
    await page.getByLabel("Quantity Food", { exact: true }).check();
    await step(page, "Pricing & Quantity");
    await page.getByLabel("Minimum guests", { exact: true }).fill("150");
    await page.getByLabel("Quantity increment", { exact: true }).fill("10");
    await page.getByLabel("Maximum guests (optional)").fill("500");
    await page.getByLabel("Price per pax (PHP)").fill("850.00");
    await savePackage(page);
    const sourceUrl = page.url();
    await page.goto("/admin/catering-packages");
    await createPackage(page, "Independent Copy");
    await page.getByLabel("Quantity Food", { exact: true }).check();
    await step(page, "Pricing & Quantity");
    await page
        .getByLabel("Copy quantity settings from")
        .selectOption({ label: "Quantity Source" });
    await expect(
        page.getByLabel("Minimum guests", { exact: true }),
    ).toHaveValue("150");
    await page.getByLabel("Minimum guests", { exact: true }).fill("100");
    await page.getByLabel("Price per pax (PHP)").fill("750.00");
    await savePackage(page);
    await page
        .getByRole("button", { name: "Edit Package", exact: true })
        .click();
    await step(page, "Pricing & Quantity");
    await expect(
        page.getByLabel("Minimum guests", { exact: true }),
    ).toHaveValue("100");
    await page.goto(sourceUrl);
    await page
        .getByRole("button", { name: "Edit Package", exact: true })
        .click();
    await step(page, "Pricing & Quantity");
    await expect(
        page.getByLabel("Minimum guests", { exact: true }),
    ).toHaveValue("150");
});
