import { test, expect } from "@playwright/test";
import { randomBytes } from "node:crypto";
import { execFileSync } from "node:child_process";
import { login, createPackage } from "./workflow";

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

test("inline category creation, contextual food dialog and mobile keyboard navigation", async ({
    page,
}) => {
    await login(page, run, password);
    await page.screenshot({
        path: "test-results/modern-dashboard.png",
        fullPage: true,
    });
    await page.goto("/admin/food-catalog");
    await page
        .getByRole("button", { name: "Add Food Item", exact: true })
        .click();
    const dialog = page.getByRole("dialog");
    await dialog.getByLabel("Food item name").fill("Steamed Rice");
    await dialog
        .getByLabel("Food category", { exact: true })
        .selectOption("new");
    await dialog.getByLabel("New category name").fill("Staples");
    await expect(page.getByRole("dialog")).toHaveCount(1);
    await page.screenshot({
        path: "test-results/inline-food-category.png",
        fullPage: true,
    });
    await dialog.getByRole("button", { name: "Create food item" }).click();
    await expect(dialog).not.toBeVisible();
    await page.reload();
    await expect(
        page.getByRole("heading", { name: "Steamed Rice", exact: true }),
    ).toBeVisible();
    const add = page.getByRole("button", {
        name: "Add Food to Staples",
        exact: true,
    });
    await add.focus();
    await page.keyboard.press("Enter");
    await expect(
        dialog
            .getByLabel("Food category", { exact: true })
            .locator("option:checked"),
    ).toHaveText("Staples");
    await page.keyboard.press("Tab");
    await expect(dialog).toBeVisible();
    await page.keyboard.press("Escape");
    await expect(dialog).not.toBeVisible();
    await expect(add).toBeFocused();
    await page.setViewportSize({ width: 390, height: 844 });
    await page.screenshot({
        path: "test-results/mobile-food-catalog.png",
        fullPage: true,
    });
    expect(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= window.innerWidth,
        ),
    ).toBe(true);
    await page.getByRole("button", { name: "Open navigation" }).click();
    const navigation = page.getByRole("dialog");
    await expect(
        navigation.getByRole("link", { name: "Dashboard", exact: true }),
    ).toBeVisible();
    await page.screenshot({
        path: "test-results/mobile-navigation.png",
        fullPage: true,
    });
    await page.keyboard.press("Escape");
    await expect(navigation).not.toBeVisible();
});

test("unsaved package navigation can be cancelled or explicitly discarded", async ({
    page,
}) => {
    await login(page, run, password);
    await page.goto("/admin/catering-packages");
    await createPackage(page, "Discard confirmation package");
    const workspace = page.url();
    await page.getByLabel("Total included food selections").fill("4");
    const home = page
        .getByRole("navigation", { name: "Administration" })
        .getByRole("link", { name: "Dashboard", exact: true });
    await home.click();
    const confirmation = page.getByRole("alertdialog", {
        name: "Discard unsaved changes?",
    });
    await expect(confirmation).toBeVisible();
    await confirmation
        .getByRole("button", { name: "Cancel", exact: true })
        .click();
    await expect(page).toHaveURL(workspace);
    await expect(page.getByLabel("Total included food selections")).toHaveValue(
        "4",
    );
    await home.click();
    await confirmation
        .getByRole("button", { name: "Discard and leave" })
        .click();
    await expect(
        page.getByRole("heading", { name: "Dashboard", exact: true }),
    ).toBeVisible();
    await page.goto(workspace);
    await expect(page.getByLabel("Package Name", { exact: true })).toHaveValue(
        "",
    );
});
