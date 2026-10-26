import { test, expect } from "@playwright/test";
import { randomBytes } from "node:crypto";
import { execFileSync } from "node:child_process";
import {
    login,
    addCategory,
    addFood,
    inlineFood,
    step,
    savePackage,
    reviewResponsive,
} from "./workflow";

const run = randomBytes(6).toString("hex");
const password = "Browser-" + randomBytes(18).toString("hex") + "A1";
const pixel = execFileSync("php", [
    "-r",
    "$image = imagecreatetruecolor(32, 32); imagepng($image); imagedestroy($image);",
]);
test.beforeAll(() =>
    execFileSync("php", ["tests/Browser/seed.php"], {
        env: {
            ...process.env,
            BROWSER_RUN: run,
            BROWSER_PASSWORD: password,
            BROWSER_LEGACY: "0",
        },
    }),
);

test("food upload preview, viewer, replacement, invalid content and removal persist", async ({
    page,
}) => {
    await login(page, run, password);
    await page.goto("/admin/food-catalog");
    await addCategory(page, "Image Foods");
    await page
        .getByRole("button", { name: "Add Food Item", exact: true })
        .click();
    const dialog = page.getByRole("dialog");
    await dialog.getByLabel("Food item name").fill("Image Rice");
    await dialog
        .getByLabel("Food category", { exact: true })
        .selectOption({ label: "Image Foods" });
    await dialog.locator("input[type=file]").setInputFiles({
        name: "rice.png",
        mimeType: "image/png",
        buffer: pixel,
    });
    await expect(dialog.locator("img")).toBeVisible();
    await dialog.getByRole("button", { name: "Create food item" }).click();
    await expect(dialog).not.toBeVisible();
    await page.reload();
    const viewer = page.getByRole("button", {
        name: "View image of Image Rice",
    });
    await viewer.click();
    await expect(dialog.locator("img")).toBeVisible();
    await page.screenshot({
        path: "test-results/food-image-viewer.png",
        fullPage: true,
    });
    await page.keyboard.press("Escape");
    await expect(viewer).toBeFocused();
    await page.getByRole("button", { name: "Edit food Image Rice" }).click();
    await dialog.locator("input[type=file]").setInputFiles({
        name: "fake.jpg",
        mimeType: "image/jpeg",
        buffer: Buffer.from("not a decoded image"),
    });
    await dialog.getByRole("button", { name: "Save food item" }).click();
    await expect(dialog.getByRole("alert").first()).toBeVisible();
    await dialog.locator("input[type=file]").setInputFiles({
        name: "replacement.png",
        mimeType: "image/png",
        buffer: pixel,
    });
    await dialog.getByRole("button", { name: "Save food item" }).click();
    await expect(dialog).not.toBeVisible();
    await page.getByRole("button", { name: "Edit food Image Rice" }).click();
    await dialog.getByLabel("Remove saved image").check();
    await dialog.getByRole("button", { name: "Save food item" }).click();
    await expect(dialog).not.toBeVisible();
    await page.reload();
    await expect(
        page.getByText("No image for Image Rice", { exact: true }),
    ).toBeVisible();
    await expect(
        page.getByRole("heading", { name: "Image Rice", exact: true }),
    ).toBeVisible();
});

test("wizard gates required fields, retains backward edits and explicitly fixed food", async ({
    page,
}) => {
    await login(page, run, password);
    await page.goto("/admin/catering-packages");
    await page
        .getByRole("link", { name: "Create Package", exact: true })
        .click();
    await page.getByRole("button", { name: "Next: Menu" }).click();
    await expect(
        page.getByLabel("Package Name", { exact: true }),
    ).toBeVisible();
    await page
        .getByLabel("Package Name", { exact: true })
        .fill("Explicit Fixed Package");
    await page.getByRole("button", { name: "Next: Menu" }).click();
    await inlineFood(page, "Choice A", "Choice Foods");
    await inlineFood(page, "Choice B", "Choice Foods");
    await page.getByLabel("Choice A", { exact: true }).check();
    await page.getByLabel("Choice B", { exact: true }).check();
    await page.getByLabel("Always include Choice A", { exact: true }).check();
    await page.getByLabel("Total included food selections").fill("2");
    await step(page, "Details");
    await expect(page.getByLabel("Package Name", { exact: true })).toHaveValue(
        "Explicit Fixed Package",
    );
    await step(page, "Menu");
    await expect(page.getByLabel("Choice B", { exact: true })).toBeChecked();
    await expect(
        page.getByLabel("Always include Choice A", { exact: true }),
    ).toBeChecked();
    await page.setViewportSize({ width: 390, height: 844 });
    await page.screenshot({
        path: "test-results/mobile-package-menu.png",
        fullPage: true,
    });
    expect(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= window.innerWidth,
        ),
    ).toBe(true);
    await step(page, "Pricing & Quantity");
    await page.getByLabel("Price per pax (PHP)").fill("500.00");
    await savePackage(page);
    await page.reload();
    await expect(
        page.getByRole("heading", { name: "Saved Draft", exact: true }),
    ).toBeVisible();
    await page
        .getByRole("button", { name: "View Details", exact: true })
        .click();
    await expect(page.getByRole("dialog")).toContainText("Draft Preview");
    await expect(page.getByRole("dialog")).toContainText("Always Include");
    await page.screenshot({
        path: "test-results/mobile-package-details.png",
        fullPage: true,
    });
    await page.keyboard.press("Escape");
    await expect(
        page.getByRole("button", { name: "View Details", exact: true }),
    ).toBeFocused();
});

test("grouped individual selling options publish, isolate revisions, restore history and archive", async ({
    page,
}) => {
    test.setTimeout(120000);
    await login(page, run, password);
    await page.goto("/admin/add-on-services");
    await page.getByRole("link", { name: "Create Add-on Service" }).click();
    await expect(
        page
            .getByRole("group", {
                name: "How will customers buy this service?",
            })
            .getByRole("radio"),
    ).toHaveCount(2);
    await page.getByLabel("Service name").fill("Individual Trays");
    await page.getByRole("radio", { name: /Sold Individually/ }).check();
    await page
        .getByRole("button", { name: "Create Service", exact: true })
        .click();
    await expect(page).toHaveURL(/\/admin\/add-on-services\/\d+$/);
    await expect(page).toHaveURL(/\/admin\/add-on-services\/\d+$/);
    await expect(
        page.getByRole("heading", { name: "Individual Trays", exact: true }),
    ).toBeVisible();
    const serviceUrl = page.url();
    await page.goto("/admin/food-catalog");
    await addCategory(page, "Tray Foods");
    await addFood(page, "Tray Chicken", "Tray Foods", "Individual Trays");
    await page.goto(serviceUrl);
    await page.getByRole("link", { name: "Create Item", exact: true }).click();
    await page
        .getByLabel("Food Item", { exact: true })
        .selectOption({ label: "Tray Chicken" });
    await expect(page.getByLabel("Item Name (optional override)")).toHaveValue(
        "",
    );
    await page.getByLabel("Sold per", { exact: true }).fill("box");
    await page.getByLabel("Price per box (PHP)").fill("1250.50");
    await page.getByLabel("Option 1 name").fill("Small Tray");
    await page.getByRole("button", { name: "Add Selling Option" }).click();
    await page.getByLabel("Option 2 name").fill("Large Tray");
    await page.getByLabel("Price for option 2 (PHP)").fill("1750");
    await page.getByLabel("Minimum Purchase", { exact: true }).fill("2");
    await reviewResponsive(page, "individual-item-editor");
    await page.getByRole("button", { name: "Save Item Draft" }).click();
    await expect(page).toHaveURL(/\/admin\/items\/\d+$/);
    await expect(page.getByText(/Draft Preview — not published/)).toBeVisible();
    const itemUrl = page.url();
    await expect(
        page.getByRole("heading", { name: "Tray Chicken", exact: true }),
    ).toBeVisible();
    await expect(
        page.getByRole("list", { name: "Selling option prices" }),
    ).toContainText("Large Tray");
    await expect(
        page.getByRole("list", { name: "Selling option prices" }),
    ).toContainText("1,750.00");
    await page
        .getByRole("button", { name: "Publish Item", exact: true })
        .click();
    await page
        .getByRole("alertdialog")
        .getByRole("button", { name: "Confirm Publication" })
        .click();
    await page.reload();
    await expect(
        page.getByText("This published item is available.", { exact: true }),
    ).toBeVisible();
    await page.evaluate(() => window.scrollTo(0, 0));
    await page.screenshot({
        path: "test-results/individual-item-details.png",
        fullPage: true,
    });
    await page.getByRole("link", { name: "Edit Item", exact: true }).click();
    await page.getByLabel("Price per box (PHP)").fill("1500.00");
    await page.getByLabel("Price for option 2 (PHP)").fill("1800");
    await page.getByRole("button", { name: "Save Item Draft" }).click();
    await expect(page.getByText(/1,250.50 per box/)).toBeVisible();
    await expect(page.getByText(/1,750.00 per box/)).toBeVisible();
    await page.getByRole("link", { name: "Preview Draft Changes" }).click();
    await expect(page.getByText(/1,500.00 per box/)).toBeVisible();
    await expect(page.getByText(/1,800.00 per box/)).toBeVisible();
    await page
        .getByRole("button", { name: "Publish Item", exact: true })
        .click();
    await page
        .getByRole("alertdialog")
        .getByRole("button", { name: "Confirm Publication" })
        .click();
    const changes = page.getByRole("region", { name: "Commercial changes" });
    await changes.getByText(/Version 2 — published by/).click();
    await expect(changes.locator("details[open]").first()).toContainText(
        "PHP 1750.00",
    );
    await expect(changes.locator("details[open]").first()).toContainText(
        "PHP 1800.00",
    );
    await changes
        .getByText("Editing and publication activity", { exact: true })
        .click();
    await expect(changes).toContainText("draft updated");
    await reviewResponsive(page, "commercial-history");
    await page
        .getByRole("button", { name: "Create Draft from Version 1" })
        .click();
    await expect(page.getByLabel("Sold per", { exact: true })).toHaveValue(
        "box",
    );
    await expect(page.getByLabel("Price per box (PHP)")).toHaveValue("1250.50");
    await expect(page.getByLabel("Price for option 2 (PHP)")).toHaveValue(
        "1750.00",
    );
    await page.goto(itemUrl);
    await expect(page.getByText(/1,500.00 per box/)).toBeVisible();
    await page
        .getByRole("button", { name: "Disable Item", exact: true })
        .click();
    await expect(page.getByText(/This item is disabled/)).toBeVisible();
    await page
        .getByRole("button", { name: "Archive Item", exact: true })
        .click();
    await page
        .getByRole("alertdialog")
        .getByRole("button", { name: "Confirm Archive" })
        .click();
    await page.reload();
    await expect(page.getByText(/This item is archived/)).toBeVisible();
    await expect(
        page.getByRole("link", { name: "Published Version 1", exact: true }),
    ).toBeVisible();
    await expect(
        page.getByRole("link", { name: "Published Version 2", exact: true }),
    ).toBeVisible();
    await expect(
        page.getByRole("link", { name: "Abandoned Draft 3", exact: true }),
    ).toBeVisible();
});
