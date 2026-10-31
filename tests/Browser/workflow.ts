import { expect, type Page } from "@playwright/test";

export async function configureService(
    page: Page,
    format = "separate_servings",
    container = "Tray",
) {
    const formatField = page.getByLabel("How are foods provided?");
    if (await formatField.isDisabled())
        await expect(formatField).toHaveValue(format);
    else await formatField.selectOption(format);
    const select = page.getByRole("combobox", {
        name: "Unit",
        exact: true,
    });
    if (
        await select
            .locator("option")
            .filter({ hasText: new RegExp(`^${container}$`) })
            .count()
    ) {
        await select.selectOption({ label: container });
    } else {
        await page
            .getByRole("button", {
                name: "Add Measurement Unit",
                exact: true,
            })
            .click();
        const dialog = page.getByRole("dialog", {
            name: "Create Measurement Unit",
            exact: true,
        });
        await dialog.getByLabel("Unit name").fill(container);
        await dialog
            .getByLabel("Abbreviation")
            .fill(container.toLowerCase().replaceAll(" ", "-"));
        await dialog
            .getByRole("button", {
                name: "Create Measurement Unit",
                exact: true,
            })
            .click();
        await expect(dialog).not.toBeVisible();
    }
}

export async function customizeFood(page: Page, name: string) {
    const summary = page
        .getByRole("region", { name: `Food Options for ${name}`, exact: true })
        .getByText("Food Options", { exact: true });
    if (
        !(await summary.evaluate(
            (el) => (el.parentElement as HTMLDetailsElement).open,
        ))
    )
        await summary.click();
}

export async function openPortions(page: Page) {
    const summary = page
        .locator('details[aria-label="Advanced Package Settings"]')
        .getByText(/^Advanced Package Settings/);
    if (
        !(await summary.evaluate(
            (el) => (el.parentElement as HTMLDetailsElement).open,
        ))
    )
        await summary.click();
}

export async function reviewResponsive(page: Page, name: string) {
    const original = page.viewportSize();
    await expect(page.locator("[data-sonner-toast]")).toHaveCount(0, {
        timeout: 10000,
    });
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
            const customization = page
                .getByText("Food Options", {
                    exact: true,
                })
                .first();
            if (await customization.count()) {
                await customization.scrollIntoViewIfNeeded();
                await page.screenshot({
                    path: `test-results/${name}-customization-os-${colorScheme}-${layout}.png`,
                    fullPage: true,
                });
            }
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
    // A fresh PostgreSQL schema can still be releasing its advisory lock when
    // the first real browser login is submitted. Wait for that navigation
    // rather than treating a slow, valid authentication response as a failure.
    await expect(page).toHaveURL(/\/admin(?:\/home)?$/, { timeout: 15000 });
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
        page
            .getByRole("heading", { name, exact: true })
            .or(
                page
                    .getByRole("status")
                    .filter({ hasText: `${name} was saved outside` }),
            ),
    ).toBeVisible();
}
export async function createPackage(page: Page, name: string) {
    await page
        .getByRole("link", { name: "Create Package", exact: true })
        .click();
    await page.waitForURL(
        /\/admin\/(?:catering-packages\/create|packages\/editor\?service=\d+)$/,
    );
    if (/\/admin\/catering-packages\/create$/.test(page.url())) {
        await expect(page).toHaveURL(/\/admin\/catering-packages\/create$/);
        await page.getByLabel("Package Name", { exact: true }).fill(name);
        await page
            .getByRole("button", { name: "Save Details & Continue" })
            .click();
        await expect(page).toHaveURL(
            /\/admin\/catering-packages\/\d+\/pricing$/,
        );
        await page
            .getByRole("button", { name: "Save & Continue to Menu" })
            .click();
        await expect(page).toHaveURL(/\/admin\/catering-packages\/\d+\/menu$/);
        await expect(
            page.getByLabel("Total included food selections"),
        ).toBeVisible();
        return;
    }
    await expect(page).toHaveURL(/\/admin\/packages\/editor\?service=\d+$/);
    await page.getByLabel("Package Name", { exact: true }).fill(name);
    const next = page.getByRole("button", { name: "Next: Menu", exact: true });
    if (await next.count()) await next.click();
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
    const pageNavigation = page.getByRole("navigation", {
        name: "Package configuration pages",
    });
    // An Inertia redirect from the compatibility editor can briefly retain its
    // old URL after an Edit Package link is clicked. The routed workflow's
    // navigation is the reliable signal that the Main Catering page is ready.
    if (
        await expect(pageNavigation)
            .toBeVisible({ timeout: 5000 })
            .then(() => true)
            .catch(() => false)
    ) {
        const mainCateringLabels: Record<
            Exclude<typeof name, "Review & Publish">,
            string
        > = {
            Details: "Package Details",
            Menu: "Menu Configuration",
            "Pricing & Quantity": "Pricing & Quantity",
            "Review & Save": "Review & Save",
        };
        const target = pageNavigation.getByRole("link", {
            name: mainCateringLabels[name],
            exact: true,
        });
        await target.click();
        await expect(target).toHaveAttribute("aria-current", "page");
        return;
    }
    let navigation = page.getByRole("navigation", {
        name: "Package setup steps",
    });
    if (!(await navigation.count())) {
        await page.waitForURL(
            /\/admin\/packages\/(?:editor|products\/\d+\/edit)/,
        );
        navigation = page.getByRole("navigation", {
            name: "Package setup steps",
        });
    }
    if (await navigation.count()) {
        const target = navigation.getByRole("button", {
            name: new RegExp(name.replaceAll("&", "\\&")),
        });
        await target.click();
        await expect(target).toHaveAttribute("aria-current", "step");
    }
}
export async function savePackage(page: Page) {
    if (/\/admin\/catering-packages\/\d+\//.test(page.url())) {
        if (page.url().endsWith("/menu")) {
            await page
                .getByRole("button", { name: "Save & Continue to Review" })
                .click();
            await expect(page).toHaveURL(/\/review$/);
        } else if (page.url().endsWith("/pricing")) {
            await page
                .getByRole("button", { name: "Save & Continue to Menu" })
                .click();
            await expect(page).toHaveURL(/\/menu$/);
            await page
                .getByRole("link", { name: "Review & Save", exact: true })
                .click();
        }
        await page
            .getByRole("link", { name: "Save Draft & Return", exact: true })
            .click();
        await expect(page).toHaveURL(/\/admin\/catering-packages\/\d+$/);
        return;
    }
    await step(page, "Review & Save");
    const saved = page.waitForResponse(
        (response) =>
            response.request().method() !== "GET" &&
            /\/configurations?$/.test(new URL(response.url()).pathname),
    );
    await page.getByRole("button", { name: "Save Draft", exact: true }).click();
    await saved;
    await expect(page).toHaveURL(
        /\/admin\/(?:catering-packages|packages\/products)\/\d+$/,
    );
    await expect(
        page.getByRole("heading", { name: "Saved Draft", exact: true }),
    ).toBeVisible();
}
export async function inlineFood(page: Page, name: string, category: string) {
    const addFoodItem = page.getByRole("button", {
        name: "Add Food Item",
        exact: true,
    });
    await addFoodItem.click();
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
    const categoryPicker = page.getByRole("combobox", {
        name: /^Category/,
    });
    await categoryPicker.click();
    await categoryPicker.fill(category);
    await expect(
        page.getByRole("option", {
            name: new RegExp(
                `^${category.replace(/[.*+?^${}()|[\]\\]/g, "\\$&")} \\(`,
            ),
        }),
    ).toBeVisible();
    await categoryPicker.press("Enter");
    if (/\/admin\/catering-packages\/\d+\/menu$/.test(page.url())) {
        await page.getByLabel(name, { exact: true }).check();
        return;
    }
    await page.getByLabel(name, { exact: true }).check();
}

/** Uses the Phase 5.1 guided picker instead of the retired category panels. */
export async function filterPackageFoodsByCategory(page: Page, name: string) {
    const category = page.getByRole("combobox", {
        name: /^Category/,
    });
    const current = await category.inputValue();
    if (
        current === name ||
        (name !== "All eligible foods" && current.startsWith(`${name} (`))
    )
        return;
    await category.click();
    await expect(category).toHaveAttribute("aria-expanded", "true");
    await expect(category).toHaveValue("");
    await category.fill(name);
    const option = page.getByRole("option", {
        name:
            name === "All eligible foods"
                ? name
                : new RegExp(
                      `^${name.replace(/[.*+?^${}()|[\]\\]/g, "\\$&")} \\(`,
                  ),
    });
    await expect(option).toBeVisible();
    // SearchableSelect keeps focus on its combobox. Choosing the highlighted
    // filtered option with Enter exercises its documented keyboard path and
    // avoids a pointer race while the menu is repositioning.
    await category.press("Enter");
    await expect(category).toHaveAttribute("aria-expanded", "false");
}

export async function selectAllVisiblePackageFoods(page: Page) {
    const categoryPage = page.getByRole("button", {
        name: "Select this page",
        exact: true,
    });
    if (await categoryPage.count()) {
        await categoryPage.click();
        return;
    }

    await page.getByRole("button", { name: "Select all shown" }).click();
}

export async function requireOneFromPackageCategory(page: Page, name: string) {
    if (/\/admin\/catering-packages\/\d+\/menu$/.test(page.url())) {
        await page
            .getByRole("button", { name: new RegExp(`^${name} \\(`) })
            .click();
        await page
            .getByRole("dialog", { name: `Configure ${name} Foods` })
            .getByLabel("Require customer choice")
            .check();
        await page.getByRole("button", { name: "Close dialog" }).click();
        return;
    }
    const selectedCategory = page
        .getByRole("region", { name: "Selected foods", exact: true })
        .locator("section")
        .filter({
            has: page.getByRole("heading", { name, exact: true }),
        });
    if (await selectedCategory.count()) {
        await selectedCategory
            .getByRole("button", {
                name: "Require customer choice",
                exact: true,
            })
            .click();
        return;
    }

    const group = page
        .getByLabel("Customer Choice Groups")
        .locator("article")
        .filter({
            has: page.getByRole("heading", { name, exact: true }),
        });
    await group
        .getByRole("button", { name: "Require customer choice", exact: true })
        .click();
}
