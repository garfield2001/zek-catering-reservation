import { configureService, customizeFood, openPortions } from "./workflow";
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
    filterPackageFoodsByCategory,
    requireOneFromPackageCategory,
    savePackage,
    selectAllVisiblePackageFoods,
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
    await page.getByRole("link", { name: "Create Add-on Service" }).click();
    await page.getByLabel("Service name").fill("Tray Market");
    await page.getByRole("radio", { name: /Sold Individually/ }).check();
    await page
        .getByRole("button", { name: "Create Service", exact: true })
        .click();
    await expect(page).toHaveURL(/\/admin\/add-on-services\/\d+$/);
    await expect(page.getByRole("link", { name: "Create Item" })).toBeVisible();
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

test("direct-sale bulk selection keeps foods across categories and creates independent drafts", async ({
    page,
}) => {
    await login(page, run, password);
    await page.goto("/admin/add-on-services");
    await page.getByRole("link", { name: "Create Add-on Service" }).click();
    await page.getByLabel("Service name").fill("Bulk Counter");
    await page.getByRole("radio", { name: /Sold Individually/ }).check();
    await page
        .getByRole("button", { name: "Create Service", exact: true })
        .click();
    await expect(page).toHaveURL(/\/admin\/add-on-services\/\d+$/);
    await expect(page).toHaveURL(/\/admin\/add-on-services\/\d+$/);
    const serviceUrl = page.url();

    await page.goto("/admin/food-catalog");
    await addCategory(page, "Bulk Chicken");
    await addFood(page, "Bulk Roast", "Bulk Chicken");
    await addFood(page, "Bulk Garlic", "Bulk Chicken");
    await addCategory(page, "Bulk Beef");
    await addFood(page, "Bulk Caldereta", "Bulk Beef");

    await page.goto(serviceUrl);
    await page.getByRole("link", { name: "Add Foods to Sell" }).click();
    await filterPackageFoodsByCategory(page, "Bulk Chicken");
    await page.getByRole("button", { name: /Select all shown \(2\)/ }).click();
    await expect(page.getByText("Selected Foods — 2 items")).toBeVisible();
    await filterPackageFoodsByCategory(page, "All eligible foods");
    await page.getByLabel("Search all eligible foods").fill("Bulk Caldereta");
    await page
        .locator("article")
        .filter({ hasText: "Bulk Caldereta" })
        .getByLabel("Add")
        .check();
    await expect(page.getByText("Selected Foods — 3 items")).toBeVisible();
    await page
        .getByRole("button", { name: "Configure Selected Foods" })
        .click();
    const prices = page.getByLabel("Price (PHP)");
    await prices.nth(0).fill("750");
    await prices.nth(1).fill("800");
    await prices.nth(2).fill("900");
    await page.getByRole("button", { name: "Review selected foods" }).click();
    await page.getByRole("button", { name: "Save 3 Drafts" }).click();
    await expect(page).toHaveURL(serviceUrl);
    await expect(
        page.getByRole("heading", { name: "Bulk Roast", exact: true }),
    ).toBeVisible();
    await expect(
        page.getByRole("heading", { name: "Bulk Garlic", exact: true }),
    ).toBeVisible();
    await expect(
        page.getByRole("heading", { name: "Bulk Caldereta", exact: true }),
    ).toBeVisible();
});

test("Measurement Unit management supports safe lifecycle for unused units", async ({
    page,
}) => {
    await login(page, run, password);
    await page.goto("/admin/food-catalog");
    await page.getByText("Catalog Settings", { exact: true }).click();
    await page
        .getByRole("button", { name: "Manage Measurement Units" })
        .click();
    const dialog = page.getByRole("dialog", {
        name: "Manage Measurement Units",
        exact: true,
    });
    await page.setViewportSize({ width: 390, height: 844 });
    await expect(dialog).toBeVisible();
    expect(
        await dialog.evaluate(
            (element) => element.scrollWidth <= element.clientWidth + 1,
        ),
    ).toBe(true);
    await page.setViewportSize({ width: 1440, height: 1000 });
    await dialog
        .getByRole("button", { name: "Add Measurement Unit", exact: true })
        .click();
    await dialog.getByLabel("Unit name").fill("Test basket");
    await dialog.getByLabel("Symbol").fill("basket");
    await dialog
        .getByRole("button", { name: "Create Measurement Unit", exact: true })
        .click();
    await dialog.getByLabel("Search units").fill("basket");
    const row = dialog.getByRole("row", { name: /Test basket/ });
    await expect(row).toContainText("Active");
    await row.getByRole("button", { name: "Edit or deactivate" }).click();
    await dialog.getByLabel("Active Measurement Unit").uncheck();
    await dialog
        .getByRole("button", { name: "Save Measurement Unit", exact: true })
        .click();
    await expect(row).toContainText("Inactive");
    await row.getByRole("button", { name: "Edit or reactivate" }).click();
    await dialog.getByLabel("Active Measurement Unit").check();
    await dialog
        .getByRole("button", { name: "Save Measurement Unit", exact: true })
        .click();
    await expect(row).toContainText("Active");
    await row.getByRole("button", { name: "Delete", exact: true }).click();
    await page
        .getByRole("alertdialog", { name: "Delete Measurement Unit?" })
        .getByRole("button", { name: "Delete Unit", exact: true })
        .click();
    await expect(page.getByRole("row", { name: /Test basket/ })).toHaveCount(0);
});

test("service setup keeps business details and synchronizes managed and quick-created units", async ({
    page,
}) => {
    const managed = `Service tray ${run}`;
    const quick = `Service pack ${run}`;
    await login(page, run, password);
    await page.goto("/admin/add-on-services");
    await page.getByRole("link", { name: "Create Add-on Service" }).click();
    const dialog = page;
    await dialog.getByLabel("Service name").fill("Presentation service");
    await dialog
        .getByLabel("Description (optional)")
        .fill("Preserve this unsaved service detail.");
    await expect(
        dialog.getByText(
            "Each food is packed separately. Every included or selected food uses this container unless you configure an exception.",
        ),
    ).toBeVisible();
    await dialog
        .getByRole("button", { name: "Manage Measurement Units", exact: true })
        .click();
    const manager = page.getByRole("dialog", {
        name: "Manage Measurement Units",
        exact: true,
    });
    await manager
        .getByRole("button", { name: "Add Measurement Unit", exact: true })
        .click();
    await manager.getByLabel("Unit name").fill(managed);
    await manager.getByLabel("Symbol").fill(`st${run.slice(0, 5)}`);
    await manager
        .getByRole("button", { name: "Create Measurement Unit", exact: true })
        .click();
    await expect(
        manager.getByRole("row", { name: new RegExp(managed) }),
    ).toBeVisible();
    await manager.getByRole("button", { name: "Close dialog" }).click();
    await expect(dialog.getByLabel("Service name")).toHaveValue(
        "Presentation service",
    );
    await expect(dialog.getByLabel("Description (optional)")).toHaveValue(
        "Preserve this unsaved service detail.",
    );
    await expect(
        dialog.getByRole("option", { name: managed, exact: true }),
    ).toHaveCount(1);
    await dialog
        .getByRole("group", { name: "Default Presentation per Food" })
        .getByRole("combobox")
        .selectOption({ label: managed });
    await expect(dialog.getByRole("status")).toContainText(
        new RegExp(managed, "i"),
    );
    await dialog
        .getByRole("button", { name: "Add Measurement Unit", exact: true })
        .click();
    const quickDialog = page.getByRole("dialog", {
        name: "Create Measurement Unit",
        exact: true,
    });
    await quickDialog.getByLabel("Unit name").fill(quick);
    await quickDialog.getByLabel("Abbreviation").fill(`sp${run.slice(0, 5)}`);
    await quickDialog
        .getByRole("button", { name: "Create Measurement Unit", exact: true })
        .click();
    await expect(
        dialog
            .getByRole("group", { name: "Default Presentation per Food" })
            .getByRole("combobox")
            .locator("option:checked"),
    ).toHaveText(quick);
    await dialog
        .getByRole("button", { name: "Manage Measurement Units", exact: true })
        .click();
    await expect(
        page
            .getByRole("dialog", {
                name: "Manage Measurement Units",
                exact: true,
            })
            .getByRole("row", { name: new RegExp(quick) }),
    ).toBeVisible();
});

test("dynamic packed service opens its workspace and creates a service-scoped package", async ({
    page,
}) => {
    await login(page, run, password);
    await page.goto("/admin/add-on-services");
    await page.getByRole("link", { name: "Create Add-on Service" }).click();
    await page.getByLabel("Service name").fill("Lunch Boxes");
    await configureService(page, "combined_contents", "Meal Box");
    await page.getByRole("radio", { name: /Sold by Package/ }).check();
    await page
        .getByRole("button", { name: "Create Service", exact: true })
        .click();
    await expect(page).toHaveURL(/\/admin\/add-on-services\/\d+$/);
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
    await page.getByLabel("Price per complete Meal Box (PHP)").fill("125.50");
    await page.getByLabel("Minimum complete Meal Box units").fill("10");
    await expect(page.getByLabel("Meal selections")).toHaveCount(0);
    await savePackage(page);
    await page.getByRole("link", { name: "Edit Package", exact: true }).click();
    await step(page, "Pricing & Quantity");
    await expect(
        page.getByLabel("Price per complete Meal Box (PHP)"),
    ).toHaveValue("125.50");
    await expect(
        page.getByLabel("Minimum complete Meal Box units"),
    ).toHaveValue("10");
    await expect(page.getByLabel("Meal selections")).toHaveCount(0);
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
    await page.getByRole("link", { name: "Create Add-on Service" }).click();
    await page.getByLabel("Service name").fill("Bellychon Package");
    await configureService(page);
    await page.getByRole("radio", { name: /Sold by Package/ }).check();
    await page
        .getByRole("button", { name: "Create Service", exact: true })
        .click();
    await expect(page).toHaveURL(/\/admin\/add-on-services\/\d+$/);
    await createPackage(page, "Package A");
    await inlineFood(page, "Lechon Belly", "Belly");
    await inlineFood(page, "Roast Chicken", "Chicken");
    await inlineFood(page, "Chicken BBQ", "Chicken");
    await page.getByLabel("Total included food selections").fill("2");
    await filterPackageFoodsByCategory(page, "Belly");
    await selectAllVisiblePackageFoods(page);
    await requireOneFromPackageCategory(page, "Belly");
    await filterPackageFoodsByCategory(page, "Chicken");
    await selectAllVisiblePackageFoods(page);
    await requireOneFromPackageCategory(page, "Chicken");
    await customizeFood(page, "Lechon Belly");
    const lechonOptions = page.getByRole("region", {
        name: "Food Options for Lechon Belly",
        exact: true,
    });
    await lechonOptions.getByLabel("Set a fixed portion").check();
    await lechonOptions
        .getByRole("button", { name: "Manage Measurement Units", exact: true })
        .click();
    const unitManager = page.getByRole("dialog", {
        name: "Manage Measurement Units",
        exact: true,
    });
    await expect(unitManager).toBeVisible();
    await unitManager.getByRole("button", { name: "Close dialog" }).click();
    await expect(lechonOptions.getByLabel("Set a fixed portion")).toBeChecked();
    await expect(
        page
            .getByRole("region", { name: "Selected foods", exact: true })
            .getByLabel("Always include Lechon Belly", { exact: true }),
    ).not.toBeChecked();
    await lechonOptions
        .getByRole("button", { name: "Add Measurement Unit", exact: true })
        .click();
    const kilogram = page.getByRole("dialog", {
        name: "Create Measurement Unit",
        exact: true,
    });
    await kilogram.getByLabel("Unit name").fill("Kilogram");
    await kilogram.getByLabel("Abbreviation").fill("kg");
    await kilogram
        .getByRole("button", { name: "Create Measurement Unit", exact: true })
        .click();
    await lechonOptions.getByLabel("Amount").fill("3");
    await expect(page.getByLabel("Menu summary")).toContainText(
        "Belly: exactly 1",
    );
    await step(page, "Pricing & Quantity");
    await page
        .getByLabel("Price per complete package set (PHP)")
        .fill("4500.00");
    await page.getByLabel("Minimum complete package set units").fill("1");
    await page
        .getByLabel(
            "Estimated people served per complete package set (optional)",
        )
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
    const publishedHistory = page.locator("#history details").first();
    if ((await publishedHistory.getAttribute("open")) === null)
        await publishedHistory.locator("summary").click();
    await expect(
        publishedHistory.getByText(/Lechon Belly.*Fixed inclusion/),
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
    await page.getByRole("link", { name: "Create Add-on Service" }).click();
    await page.getByLabel("Service name").fill("Tea Sets");
    await configureService(page);
    await page.getByRole("radio", { name: /Sold by Package/ }).check();
    await page
        .getByRole("button", { name: "Create Service", exact: true })
        .click();
    await expect(page).toHaveURL(/\/admin\/add-on-services\/\d+$/);
    await createPackage(page, "Afternoon Set");
    await inlineFood(page, "Cheese Sandwich", "Snacks");
    await page.getByLabel("Cheese Sandwich", { exact: true }).check();
    await page
        .getByLabel("Always include Cheese Sandwich", { exact: true })
        .check();
    await step(page, "Pricing & Quantity");
    await page
        .getByLabel("Price per complete package set (PHP)")
        .fill("120.00");
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
    await page.getByRole("link", { name: "Edit Package", exact: true }).click();
    await step(page, "Pricing & Quantity");
    await expect(
        page.getByLabel("Minimum guests", { exact: true }),
    ).toHaveValue("100");
    await page.goto(sourceUrl);
    await page.getByRole("link", { name: "Edit Package", exact: true }).click();
    await step(page, "Pricing & Quantity");
    await expect(
        page.getByLabel("Minimum guests", { exact: true }),
    ).toHaveValue("150");
});
