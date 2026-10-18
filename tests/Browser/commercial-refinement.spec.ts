import { test, expect } from "@playwright/test";
import { randomBytes } from "node:crypto";
import { execFileSync } from "node:child_process";
import {
    login,
    createPackage,
    inlineFood,
    step,
    savePackage,
    addCategory,
    reviewResponsive,
} from "./workflow";

const run = randomBytes(6).toString("hex");
const password = "Browser-" + randomBytes(18).toString("hex") + "A1";
test("category modal preserves the filtered catalog URL and explains deactivation without changing foods", async ({
    page,
}) => {
    await login(page, run, password);
    await page.goto("/admin/food-catalog?q=NoMatchingFood&status=active");
    const url = page.url();
    await addCategory(page, "Category Modal Context");
    await expect(page).toHaveURL(url);
    await page.getByText("Catalog Settings", { exact: true }).click();
    await page
        .getByRole("button", { name: "Edit category Category Modal Context" })
        .click();
    await page.getByLabel("Active category", { exact: true }).uncheck();
    await expect(
        page
            .getByRole("status")
            .filter({ hasText: "Before disabling or archiving" }),
    ).toContainText("0 Food Items");
    await page
        .getByRole("button", { name: "Save category", exact: true })
        .click();
    await expect(page.getByRole("dialog")).not.toBeVisible();
    await expect(page).toHaveURL(url);
    await page.reload();
    await expect(
        page.getByRole("option", { name: /Category Modal Context.*Inactive/ }),
    ).toHaveCount(1);
});
test("service creation preserves filters and light-only surfaces ignore the operating-system theme", async ({
    page,
}) => {
    await login(page, run, password);
    await page.goto("/admin/add-on-services?q=UnmatchedVisualFilter");
    const workspace = page.url();
    await page.getByRole("button", { name: "Create Add-on Service" }).click();
    const dialog = page.getByRole("dialog");
    await dialog.getByLabel("Service name").fill("Visual context service");
    for (const colorScheme of ["light", "dark"] as const) {
        await page.emulateMedia({ colorScheme });
        await page.setViewportSize({ width: 390, height: 844 });
        await expect(dialog).toBeVisible();
        expect(
            await page.evaluate(
                () => document.documentElement.scrollWidth <= window.innerWidth,
            ),
        ).toBe(true);
        expect(
            await dialog.evaluate((el) => el.scrollWidth <= el.clientWidth + 1),
        ).toBe(true);
        expect(
            await page.evaluate(
                () => getComputedStyle(document.documentElement).colorScheme,
            ),
        ).toBe("light");
        expect(
            await dialog.evaluate((el) => getComputedStyle(el).backgroundColor),
        ).toBe("rgb(255, 255, 255)");
        await page.screenshot({
            path: `test-results/service-modal-os-${colorScheme}-mobile.png`,
            fullPage: true,
        });
    }
    await dialog
        .getByRole("button", { name: "Create Service", exact: true })
        .click();
    await expect(dialog).not.toBeVisible();
    await expect(page).toHaveURL(workspace);
    await expect(page.getByLabel("Search services")).toHaveValue(
        "UnmatchedVisualFilter",
    );
    await page
        .getByRole("link", { name: "Open newly created service" })
        .click();
    await expect(
        page.getByRole("heading", {
            name: "Visual context service",
            exact: true,
        }),
    ).toBeVisible();
    await page.getByRole("button", { name: "Edit", exact: true }).click();
    await page
        .getByLabel("Enabled for new configuration and selection")
        .uncheck();
    await expect(
        page
            .getByRole("status")
            .filter({ hasText: "Before disabling or archiving" }),
    ).toContainText("0 packages and 0 individual listings");
    await page
        .getByRole("button", { name: "Save changes", exact: true })
        .click();
    await page.reload();
    await expect(page.getByText("Disabled", { exact: true })).toBeVisible();
    for (const colorScheme of ["light", "dark"] as const) {
        await page.emulateMedia({ colorScheme });
        await page.setViewportSize({ width: 1440, height: 1000 });
        await page.goto("/admin/food-catalog");
        expect(
            await page.evaluate(
                () => document.documentElement.scrollWidth <= window.innerWidth,
            ),
        ).toBe(true);
        await page.screenshot({
            path: `test-results/catalog-os-${colorScheme}-desktop.png`,
            fullPage: true,
        });
    }
});
test("mixed serving defaults and numeric and named overrides survive saving and reopening", async ({
    page,
}) => {
    test.setTimeout(120000);
    await login(page, run, password);
    await page.goto("/admin/add-on-services");
    await page.getByRole("button", { name: "Create Add-on Service" }).click();
    await page.getByLabel("Service name").fill("Mixed Serving Service");
    await page.getByRole("radio", { name: /Sold by Package/ }).check();
    await page
        .getByRole("button", { name: "Create Service", exact: true })
        .click();
    await page
        .getByRole("link", { name: "Open newly created service" })
        .click();
    await createPackage(page, "Mixed Serving Package");
    for (const name of ["Mixed Roast", "Mixed Noodles", "Mixed Vegetables"])
        await inlineFood(page, name, "Mixed Foods");
    await page.getByLabel("Total included food selections").fill("3");
    const defaults = page.getByRole("region", {
        name: "Default Serving per Food",
        exact: true,
    });
    await defaults.getByLabel("Set an optional serving default").check();
    await defaults
        .getByLabel("Size label (optional)", { exact: true })
        .fill("Medium");
    const createUnit = async (
        parent: ReturnType<typeof page.getByRole>,
        name: string,
        abbreviation: string,
    ) => {
        await parent
            .getByRole("button", {
                name: "Create New Measurement Unit",
                exact: true,
            })
            .click();
        const unit = page.getByRole("dialog", {
            name: "Create Measurement Unit",
            exact: true,
        });
        await unit.getByLabel("Unit name").fill(name);
        await unit.getByLabel("Abbreviation").fill(abbreviation);
        await unit
            .getByRole("button", {
                name: "Create Measurement Unit",
                exact: true,
            })
            .click();
        await expect(unit).not.toBeVisible();
    };
    await createUnit(defaults, "Mixed Tray", "mtray");
    await expect(
        defaults
            .getByRole("combobox", { name: "Measurement Unit", exact: true })
            .locator("option:checked"),
    ).toHaveText("Mixed Tray (mtray)");
    const roast = page.getByRole("group", { name: "Mixed Roast", exact: true });
    await roast.getByLabel("Offer Size Choices for Mixed Roast").check();
    await createUnit(roast, "Mixed Kilogram", "mkg");
    await roast.getByLabel("Amount for size 1", { exact: true }).fill("3");
    await roast
        .getByRole("button", { name: "Add size for Mixed Roast" })
        .click();
    await roast.getByLabel("Amount for size 2", { exact: true }).fill("4");
    await roast
        .getByLabel("Extra price (PHP)", { exact: true })
        .nth(1)
        .fill("600");
    const noodles = page.getByRole("group", {
        name: "Mixed Noodles",
        exact: true,
    });
    await noodles.getByLabel("Offer Size Choices for Mixed Noodles").check();
    await noodles
        .getByLabel("Measurement Unit for Mixed Noodles")
        .selectOption({ label: "Mixed Tray (mtray)" });
    await noodles.getByLabel("Size label 1 (optional)").fill("Small");
    await noodles.getByLabel("Amount for size 1", { exact: true }).fill("1");
    await noodles
        .getByRole("button", { name: "Add size for Mixed Noodles" })
        .click();
    await noodles.getByLabel("Size label 2 (optional)").fill("Large");
    await noodles.getByLabel("Amount for size 2", { exact: true }).fill("1");
    await noodles
        .getByLabel("Extra price (PHP)", { exact: true })
        .nth(1)
        .fill("200");
    await reviewResponsive(page, "mixed-package-editor");
    await step(page, "Details");
    await page.getByLabel("Container count", { exact: true }).fill("1");
    await page
        .getByRole("combobox", { name: "Presentation unit", exact: true })
        .selectOption({ label: "Mixed Tray" });
    await step(page, "Pricing & Quantity");
    await page.getByLabel("Price per complete package set (PHP)").fill("1000");
    await page.getByLabel("Minimum complete package set units").fill("2");
    await savePackage(page);
    await page.reload();
    await page
        .getByRole("button", { name: "Edit Package", exact: true })
        .click();
    await step(page, "Menu");
    await expect(defaults.getByLabel("Amount per food")).toHaveValue("1.000");
    await step(page, "Details");
    await expect(
        page.getByLabel("Container count", { exact: true }),
    ).toHaveValue("1");
    await expect(
        page
            .getByRole("combobox", { name: "Presentation unit", exact: true })
            .locator("option:checked"),
    ).toHaveText("Mixed Tray");
    await step(page, "Menu");
    await expect(
        defaults.getByLabel("Size label (optional)", { exact: true }),
    ).toHaveValue("Medium");
    await expect(
        roast.getByLabel("Amount for size 2", { exact: true }),
    ).toHaveValue("4.000");
    await expect(
        roast.getByLabel("Extra price (PHP)", { exact: true }).nth(1),
    ).toHaveValue("600.00");
    await expect(noodles.getByLabel("Size label 2 (optional)")).toHaveValue(
        "Large",
    );
    await expect(
        noodles.getByLabel("Extra price (PHP)", { exact: true }).nth(1),
    ).toHaveValue("200.00");
    await expect(
        page.getByLabel("Offer Size Choices for Mixed Vegetables"),
    ).not.toBeChecked();
    await step(page, "Pricing & Quantity");
    await expect(
        page.getByLabel("Minimum complete package set units"),
    ).toHaveValue("2");
    await step(page, "Menu");
    await defaults.getByLabel("Amount per food").fill("2");
    await page
        .getByRole("button", {
            name: "Return Mixed Noodles to package default",
        })
        .click();
    await expect(
        page.getByLabel("Offer Size Choices for Mixed Noodles"),
    ).not.toBeChecked();
    await expect(
        roast.getByLabel("Amount for size 2", { exact: true }),
    ).toHaveValue("4.000");
    await savePackage(page);
    await page.reload();
    await page
        .getByRole("button", { name: "Edit Package", exact: true })
        .click();
    await step(page, "Menu");
    await expect(defaults.getByLabel("Amount per food")).toHaveValue("2.000");
    await expect(
        page.getByRole("region", {
            name: "Food Configuration for Mixed Noodles",
            exact: true,
        }),
    ).toContainText("Default — 2.000 Medium mtray");
    await expect(
        roast.getByLabel("Extra price (PHP)", { exact: true }).nth(1),
    ).toHaveValue("600.00");
});
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

test("operations preview is clearly demo data with local filters and responsive charts", async ({
    page,
}) => {
    await login(page, run, password);
    await expect(
        page.getByText("DEMO DATA / PREVIEW", { exact: true }),
    ).toBeVisible();
    const requests: string[] = [];
    page.on("request", (request) => {
        if (request.isNavigationRequest()) requests.push(request.url());
    });
    const indicators = page.getByRole("region", {
        name: "Demo performance indicators",
    });
    await expect(indicators).toContainText("₱3,440,000");
    await page.getByLabel("Demo reporting period").selectOption("6");
    await expect(indicators).toContainText("₱5,660,000");
    await page.getByRole("button", { name: "payments", exact: true }).click();
    await expect(
        page.getByRole("button", { name: "payments", exact: true }),
    ).toHaveAttribute("aria-pressed", "true");
    await page.getByLabel("Demo status").selectOption("Tentative");
    await expect(
        page.getByRole("heading", { name: "Anniversary · Sample Couple D" }),
    ).toBeVisible();
    await expect(
        page.getByRole("heading", { name: "Wedding · Sample Couple A" }),
    ).not.toBeVisible();
    expect(requests).toHaveLength(0);
    await expect(
        page.getByRole("button", { name: "New reservation", exact: true }),
    ).toBeDisabled();
    await page.evaluate(() => window.scrollTo(0, 0));
    await page.screenshot({
        path: "test-results/refinement-dashboard-desktop.png",
        fullPage: true,
    });
    await page.setViewportSize({ width: 390, height: 844 });
    expect(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= window.innerWidth,
        ),
    ).toBe(true);
    await page.screenshot({
        path: "test-results/refinement-dashboard-mobile.png",
        fullPage: true,
    });
});

test("food creation retains category, live search persists records, and dirty dialogs protect edits", async ({
    page,
}) => {
    await login(page, run, password);
    await page.goto("/admin/food-catalog");
    const add = page.getByRole("button", {
        name: "Add Food Item",
        exact: true,
    });
    await add.click();
    const dialog = page.getByRole("dialog");
    await dialog.getByLabel("Food item name").fill("Demo Test Fried Rice");
    await dialog
        .getByLabel("Food category", { exact: true })
        .selectOption("new");
    await dialog.getByLabel("New category name").fill("Demo Test Staples");
    await dialog.getByRole("button", { name: "Save & Add Another" }).click();
    await expect(dialog.getByLabel("Food item name")).toHaveValue("");
    await expect(
        dialog
            .getByLabel("Food category", { exact: true })
            .locator("option:checked"),
    ).toHaveText("Demo Test Staples");
    await dialog.getByLabel("Food item name").fill("Demo Test Steamed Rice");
    await page.keyboard.press("Escape");
    await expect(page.getByRole("alertdialog")).toBeVisible();
    await page.getByRole("button", { name: "Keep Editing" }).click();
    await expect(dialog.getByLabel("Food item name")).toHaveValue(
        "Demo Test Steamed Rice",
    );
    await dialog.getByRole("button", { name: "Create food item" }).click();
    await expect(dialog).not.toBeVisible();
    await page.reload();
    await expect(
        page.getByRole("heading", {
            name: "Demo Test Fried Rice",
            exact: true,
        }),
    ).toBeVisible();
    await expect(
        page.getByRole("heading", {
            name: "Demo Test Steamed Rice",
            exact: true,
        }),
    ).toBeVisible();
    const filtered = page.waitForResponse(
        (response) =>
            new URL(response.url()).searchParams.get("q") === "Fried" &&
            response.ok(),
    );
    await page.getByLabel("Search foods or categories").fill("Fried");
    await filtered;
    await expect(
        page.getByRole("heading", {
            name: "Demo Test Steamed Rice",
            exact: true,
        }),
    ).not.toBeVisible();
    await expect(
        page.getByRole("heading", {
            name: "Demo Test Fried Rice",
            exact: true,
        }),
    ).toBeVisible();
    await expect(page).toHaveURL(/q=Fried/);
    await add.click();
    await dialog.getByLabel("Food item name").fill("Discarded food");
    await dialog.getByRole("button", { name: "Close dialog" }).click();
    await page.getByRole("button", { name: "Discard Changes" }).click();
    await expect(dialog).not.toBeVisible();
    await expect(add).toBeFocused();
    await add.click();
    await page.keyboard.press("Escape");
    await expect(dialog).not.toBeVisible();
    await page.screenshot({
        path: "test-results/refinement-food-gallery.png",
        fullPage: true,
    });
});

test("catalog saves preserve filters and reveal hidden new foods without resetting context", async ({
    page,
}) => {
    await login(page, run, password);
    await page.goto("/admin/food-catalog");
    await page
        .getByRole("button", { name: "Add Food Item", exact: true })
        .click();
    const dialog = page.getByRole("dialog");
    await dialog.getByLabel("Food item name").fill("Context Visible Food");
    await dialog
        .getByLabel("Food category", { exact: true })
        .selectOption("new");
    await dialog.getByLabel("New category name").fill("Context Category");
    await dialog
        .getByRole("button", { name: "Create food item", exact: true })
        .click();
    await expect(dialog).not.toBeVisible();
    const visible = page
        .getByRole("article")
        .filter({ hasText: "Context Visible Food" });
    await expect(visible).toHaveAttribute("data-highlighted", "true");
    await page
        .getByRole("combobox", { name: "Category", exact: true })
        .selectOption({ label: "Context Category (1)" });
    await page.getByLabel("Search foods or categories").fill("Visible");
    await expect(page).toHaveURL(/q=Visible/);
    const filteredUrl = page.url();
    await page
        .getByRole("button", { name: "Add Food Item", exact: true })
        .click();
    await expect(
        dialog
            .getByLabel("Food category", { exact: true })
            .locator("option:checked"),
    ).toHaveText("Context Category");
    await dialog.getByLabel("Food item name").fill("Context Hidden Food");
    const started = Date.now();
    await dialog
        .getByRole("button", { name: "Create food item", exact: true })
        .click();
    await expect(dialog).not.toBeVisible();
    await expect(
        page.getByRole("link", { name: "View Newly Added or Updated Food" }),
    ).toBeVisible();
    console.log(
        `Catalog no-image save to visible confirmation: ${Date.now() - started} ms (local browser sample)`,
    );
    await expect(page).toHaveURL(filteredUrl);
    await expect(page.getByLabel("Search foods or categories")).toHaveValue(
        "Visible",
    );
    await expect(
        page.getByRole("heading", { name: "Context Hidden Food", exact: true }),
    ).not.toBeVisible();
    await page
        .getByRole("button", { name: "Edit food Context Visible Food" })
        .click();
    await dialog
        .getByLabel("Food item name")
        .fill("Context Visible Food Updated");
    await dialog
        .getByRole("button", { name: "Save food item", exact: true })
        .click();
    await expect(dialog).not.toBeVisible();
    await expect(
        page.getByRole("heading", {
            name: "Context Visible Food Updated",
            exact: true,
        }),
    ).toBeVisible();
    await expect(page).toHaveURL(filteredUrl);
    await page.reload();
    await expect(
        page.getByRole("heading", {
            name: "Context Visible Food Updated",
            exact: true,
        }),
    ).toBeVisible();
});

test("modal package creation retains inline food input and publishes persisted terms", async ({
    page,
}) => {
    await login(page, run, password);
    await page.goto("/admin/catering-packages");
    const workspace = page.url();
    await page
        .getByRole("button", { name: "Create Package", exact: true })
        .click();
    const editor = page.getByRole("dialog", {
        name: "Create Package",
        exact: true,
    });
    await editor
        .getByLabel("Package Name", { exact: true })
        .fill("Modal Acceptance Package");
    await editor
        .getByRole("button", { name: "Next: Menu", exact: true })
        .click();
    await editor.getByLabel("Total included food selections").fill("1");
    await editor
        .getByRole("button", { name: "Add Food or New Category", exact: true })
        .click();
    const food = page.getByRole("dialog", { name: "Add Food", exact: true });
    await food.getByLabel("Food item name").fill("Modal Included Food");
    await food.getByLabel("Food category", { exact: true }).selectOption("new");
    await food.getByLabel("New category name").fill("Modal Category");
    await food
        .getByRole("button", { name: "Create food item", exact: true })
        .click();
    await expect(food).not.toBeVisible();
    await expect(
        editor.getByLabel("Modal Included Food", { exact: true }),
    ).toBeChecked();
    await expect(page).toHaveURL(workspace);
    await editor
        .getByRole("button", { name: "Next: Pricing & Quantity", exact: true })
        .click();
    await editor.getByLabel("Price per pax (PHP)", { exact: true }).fill("450");
    await editor.getByLabel("Minimum guests").fill("150");
    await editor
        .getByRole("button", { name: "Next: Review & Save", exact: true })
        .click();
    await expect(editor).toContainText("Modal Acceptance Package");
    await editor
        .getByRole("button", { name: "Save & Publish", exact: true })
        .click();
    await page
        .getByRole("alertdialog")
        .getByRole("button", { name: "Save & Publish", exact: true })
        .click();
    await expect(editor).not.toBeVisible();
    await expect(page).toHaveURL(workspace);
    await page.reload();
    const card = page
        .getByRole("article")
        .filter({ hasText: "Modal Acceptance Package" });
    await expect(card).toContainText("Published");
    await card
        .getByRole("button", { name: "Edit Package", exact: true })
        .click();
    const edit = page.getByRole("dialog", {
        name: "Edit Modal Acceptance Package",
        exact: true,
    });
    await expect(edit.getByLabel("Package Name", { exact: true })).toHaveValue(
        "Modal Acceptance Package",
    );
    await edit.getByRole("button", { name: "3. Pricing & Quantity" }).click();
    await expect(
        edit.getByLabel("Price per pax (PHP)", { exact: true }),
    ).toHaveValue("450.00");
    await expect(edit.getByLabel("Minimum guests")).toHaveValue("150");
    await page.keyboard.press("Escape");
    await expect(edit).not.toBeVisible();
});
