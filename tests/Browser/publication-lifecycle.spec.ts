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

test("owner creates exact main menu, publishes immutable history, revises and archives", async ({
    page,
}) => {
    test.setTimeout(180000);
    await login(page, run, password);
    await page.goto("/admin/food-catalog");
    for (const category of ["Rice", "Drinks", "Beef", "Chicken", "Other Foods"])
        await addCategory(page, category);
    for (const [food, category] of [
        ["Fried Rice", "Rice"],
        ["Cola", "Drinks"],
        ["Juice", "Drinks"],
        ["Beef Stew", "Beef"],
        ["Beef Steak", "Beef"],
        ["Chicken BBQ", "Chicken"],
        ["Spring Rolls", "Other Foods"],
    ])
        await addFood(page, food, category);
    await page.goto("/admin/catering-packages");
    await createPackage(page, `Lifecycle package ${run}`);
    await step(page, "Details");
    await expect(page.getByLabel("What does the customer receive")).toHaveCount(
        0,
    );
    await step(page, "Menu");
    await page.getByLabel("Total included food selections").fill("5");
    for (const category of [
        "Rice",
        "Drinks",
        "Beef",
        "Chicken",
        "Other Foods",
    ]) {
        await filterPackageFoodsByCategory(page, category);
        await selectAllVisiblePackageFoods(page);
    }
    const selectedFoods = page.getByRole("region", {
        name: "Selected foods",
        exact: true,
    });
    await selectedFoods
        .getByRole("button", { name: /^Beef \(2\)$/ })
        .click();
    const beefConfiguration = page.getByRole("dialog", {
        name: "Configure Beef Foods",
    });
    await expect(
        beefConfiguration.getByText("Beef Stew", { exact: true }),
    ).toBeVisible();
    await expect(
        beefConfiguration.getByText("Beef Steak", { exact: true }),
    ).toBeVisible();
    await expect(
        beefConfiguration.getByLabel("Always include Beef Stew", {
            exact: true,
        }),
    ).not.toBeChecked();
    await page.getByRole("button", { name: "Close dialog" }).click();
    for (const category of ["Rice", "Drinks"])
        await requireOneFromPackageCategory(page, category);
    await expect(
        page.getByRole("complementary", { name: "Menu summary" }),
    ).toContainText("Fried Rice");
    await expect(
        page.getByRole("complementary", { name: "Menu summary" }),
    ).toContainText("Rice: exactly 1");
    // Inline catalog creation must keep unsaved menu selections.
    await inlineFood(page, "Fruit Salad", "Desserts");
    await selectedFoods
        .getByRole("button", { name: /^Rice \(1\)$/ })
        .click();
    await expect(
        page
            .getByRole("dialog", { name: "Configure Rice Foods" })
            .getByText("Fried Rice", { exact: true }),
    ).toBeVisible();
    await page.getByRole("button", { name: "Close dialog" }).click();
    await expect(page.getByLabel("Total included food selections")).toHaveValue(
        "5",
    );
    await expect(page.locator("#nprogress")).toHaveCount(0);
    await page.evaluate(() => window.scrollTo(0, 0));
    await page.screenshot({
        path: "test-results/main-configured-menu.png",
        fullPage: true,
    });
    await page
        .getByRole("button", { name: "Save & Continue to Review" })
        .click();
    await expect(page).toHaveURL(/\/review$/);
    await page.getByRole("link", { name: "Edit Pricing", exact: true }).click();
    await page.getByLabel("Price per pax (PHP)").fill("850.00");
    await page.getByLabel("Minimum guests", { exact: true }).fill("150");
    await page.getByRole("button", { name: "Save & Continue to Menu" }).click();
    await expect(page).toHaveURL(/\/menu$/);
    await expect(page.locator("#nprogress")).toHaveCount(0);
    await page.evaluate(() => window.scrollTo(0, 0));
    await page.screenshot({
        path: "test-results/main-pricing.png",
        fullPage: true,
    });
    await expect(
        page.getByText(/Variant|Selection Group|slot kind/i),
    ).toHaveCount(0);
    await page
        .getByRole("link", { name: "Review & Save", exact: true })
        .click();
    await expect(
        page.locator("main h1").filter({ hasText: "Review & Save" }),
    ).toBeVisible();
    await expect(page.getByText(/Exactly 5 distinct foods/)).toBeVisible();
    await expect(page.locator("#nprogress")).toHaveCount(0);
    await page.evaluate(() => window.scrollTo(0, 0));
    await page.screenshot({
        path: "test-results/main-publication-review.png",
        fullPage: true,
    });
    await page.getByRole("button", { name: "Save & Publish" }).click();
    await expect(page).toHaveURL(/\/admin\/catering-packages\/\d+$/);
    if (
        (await page
            .locator("#history details")
            .first()
            .getAttribute("open")) === null
    )
        await page.getByText("Version 1 · Published", { exact: true }).click();
    await expect(page.getByText(/Fried Rice.*Fixed inclusion/)).toBeVisible();
    await page
        .getByRole("button", { name: "Create New Draft from Version 1" })
        .click();
    await page.getByRole("link", { name: "Edit Package", exact: true }).click();
    await expect(
        page.getByRole("heading", { name: "Package Details", exact: true }),
    ).toBeVisible();
    await step(page, "Pricing & Quantity");
    await page.getByLabel("Price per pax (PHP)").fill("900.00");
    await savePackage(page);
    if (
        (await page
            .locator("#history details")
            .first()
            .getAttribute("open")) === null
    )
        await page.getByText("Version 1 · Published", { exact: true }).click();
    await expect(page.getByText(/Base Price:.*850/)).toBeVisible();
    await expect(page.locator("#nprogress")).toHaveCount(0);
    await page.evaluate(() => window.scrollTo(0, 0));
    await page.screenshot({
        path: "test-results/main-revision-history.png",
        fullPage: true,
    });
    await page
        .getByRole("button", { name: "Disable Package", exact: true })
        .click();
    await expect(
        page.getByRole("button", { name: "Enable Package", exact: true }),
    ).toBeVisible();
    await page
        .getByRole("button", { name: "Archive Package", exact: true })
        .click();
    await page
        .getByRole("alertdialog")
        .getByRole("button", { name: "Confirm Archive" })
        .click();
    await expect(page.getByText("Archived", { exact: true })).toBeVisible();
    await page.reload();
    await expect(page.getByText("Archived", { exact: true })).toBeVisible();
    const result = execFileSync(
        "php",
        ["tests/Browser/assert-publication-lifecycle.php"],
        { env: { ...process.env, BROWSER_RUN: run }, encoding: "utf8" },
    );
    expect(JSON.parse(result)).toMatchObject({
        published_version: 1,
        cloned_version: 2,
        cloned_status: "abandoned",
        archived: true,
        published_price: "85000",
        revision_price: "90000",
        fixed_food: "Fried Rice",
        total: 5,
    });
});
