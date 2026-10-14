import { expect, type Page } from "@playwright/test";

export async function login(
    page: Page,
    run: string,
    password: string,
    role = "admin",
) {
    await page.goto("/login");
    await page.getByLabel("Email address").fill(`${role}-${run}@example.test`);
    await page.getByLabel("Password", { exact: true }).fill(password);
    await page.getByRole("button", { name: "Sign in" }).click();
    await expect(page).toHaveURL(/\/admin(?:\/home)?$/);
}

export async function addCategory(page: Page, name: string) {
    await page
        .getByRole("button", { name: "Add Category", exact: true })
        .click();
    const dialog = page.getByRole("dialog");
    await dialog.getByLabel("Category name").fill(name);
    await dialog.getByRole("button", { name: "Create category" }).click();
    await expect(dialog).not.toBeVisible();
}

export async function addFood(
    page: Page,
    name: string,
    category: string,
    service?: string,
) {
    await page
        .getByRole("button", { name: "Add Food Item", exact: true })
        .click();
    const dialog = page.getByRole("dialog");
    await dialog.getByLabel("Food item name").fill(name);
    await dialog.getByLabel("Food category").selectOption({ label: category });
    if (service) await dialog.getByLabel(service, { exact: true }).check();
    await dialog.getByRole("button", { name: "Create food item" }).click();
    await expect(dialog).not.toBeVisible();
    await expect(
        page.getByRole("heading", { name, exact: true }),
    ).toBeVisible();
}
export async function createPackage(page: Page, name: string) {
    await page
        .getByRole("link", { name: "Create Package", exact: true })
        .click();
    await page.getByLabel("Package Name", { exact: true }).fill(name);
    await page.getByRole("button", { name: "Next: Menu", exact: true }).click();
    await expect(
        page.getByLabel("Total included food selections"),
    ).toBeVisible();
}
export async function step(
    page: Page,
    name:
        | "Details"
        | "Menu"
        | "Pricing & Quantity"
        | "Review & Save"
        | "Review & Publish",
) {
    if (name === "Review & Publish") {
        await savePackage(page);
        return;
    }
    await page
        .getByRole("navigation", { name: "Package setup steps" })
        .getByRole("button", { name: new RegExp(name.replaceAll("&", "\\&")) })
        .click();
}
export async function savePackage(page: Page) {
    await step(page, "Review & Save");
    await page
        .getByRole("button", { name: "Save Package", exact: true })
        .click();
    await expect(
        page.getByRole("heading", { name: "Saved Draft", exact: true }),
    ).toBeVisible();
}
export async function inlineFood(page: Page, name: string, category: string) {
    await page
        .getByRole("button", { name: "Add Food or New Category", exact: true })
        .click();
    const dialog = page.getByRole("dialog", { name: "Add Food", exact: true });
    await dialog.getByLabel("Food item name").fill(name);
    const select = dialog.getByLabel("Food category", { exact: true });
    if (
        await select
            .locator("option")
            .filter({ hasText: new RegExp("^" + category + "$") })
            .count()
    )
        await select.selectOption({ label: category });
    else {
        await select.selectOption("new");
        await dialog.getByLabel("New category name").fill(category);
    }
    await dialog.getByRole("button", { name: "Create food item" }).click();
    await expect(dialog).not.toBeVisible();
    await expect(page.getByLabel(name, { exact: true })).toBeVisible();
}
