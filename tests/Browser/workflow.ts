import { expect, type Page } from "@playwright/test";

export async function reviewResponsive(page: Page, name: string) {
    const original = page.viewportSize();
    for (const colorScheme of ["light", "dark"] as const) {
        await page.emulateMedia({ colorScheme });
        // Both OS preferences must retain the owner-approved light-only UI.
        expect(
            await page.evaluate(
                () => getComputedStyle(document.documentElement).colorScheme,
            ),
        ).toBe("light");
        for (const [layout, width, height] of [
            ["mobile", 390, 844],
            ["desktop", 1440, 1000],
        ] as const) {
            await page.setViewportSize({ width, height });
            expect(
                await page.evaluate(
                    () =>
                        document.documentElement.scrollWidth <=
                        window.innerWidth,
                ),
            ).toBe(true);
            const dialog = page.getByRole("dialog");
            if (await dialog.count()) {
                await dialog.evaluate((el) => {
                    el.scrollTop = 0;
                });
                expect(
                    await dialog.evaluate(
                        (el) => el.scrollWidth <= el.clientWidth + 1,
                    ),
                ).toBe(true);
            }
            await page.screenshot({
                path: `test-results/${name}-os-${colorScheme}-${layout}.png`,
                fullPage: true,
            });
        }
    }
    await page.emulateMedia({ colorScheme: "light" });
    if (original) await page.setViewportSize(original);
}

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
    _service?: string,
) {
    await page
        .getByRole("button", { name: "Add Food Item", exact: true })
        .click();
    const dialog = page.getByRole("dialog");
    await dialog.getByLabel("Food item name").fill(name);
    await dialog.getByLabel("Food category").selectOption({ label: category });
    // Active foods are globally available; service assignment is no longer a setup step.
    await dialog.getByRole("button", { name: "Create food item" }).click();
    await expect(dialog).not.toBeVisible();
    await expect(
        page.getByRole("heading", { name, exact: true }),
    ).toBeVisible();
}
export async function createPackage(page: Page, name: string) {
    await page
        .getByRole("button", { name: "Create Package", exact: true })
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
    const saved = page.waitForResponse(
        (response) =>
            response.request().method() !== "GET" &&
            /\/configurations?$/.test(new URL(response.url()).pathname) &&
            response.ok(),
    );
    const refreshed = page.waitForResponse(
        (response) =>
            response.request().method() === "GET" &&
            response.request().headers()["x-inertia"] === "true" &&
            response.ok(),
    );
    await page.getByRole("button", { name: "Save Draft", exact: true }).click();
    const result = await (await saved).json();
    await refreshed;
    await expect(page.getByRole("dialog")).not.toBeVisible();
    // Continue through the saved product's history workspace for lifecycle assertions.
    await page.goto(`/admin/packages/products/${result.id}`);
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
