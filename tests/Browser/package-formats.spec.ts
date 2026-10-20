import { configureService } from "./workflow";
import { test, expect, type Page } from "@playwright/test";
import { randomBytes } from "node:crypto";
import { execFileSync } from "node:child_process";
import {
    login,
    createPackage,
    inlineFood,
    step,
    savePackage,
    reviewResponsive,
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
async function persisted(page: Page, workspace: string) {
    const version = JSON.parse(
        (await page.locator("#app").getAttribute("data-page")) ?? "{}",
    ).version;
    const response = await page.request.get(workspace, {
        headers: {
            "X-Inertia": "true",
            "X-Requested-With": "XMLHttpRequest",
            "X-Inertia-Version": version,
        },
    });
    expect(response.status()).toBe(200);
    return (await response.json()).props;
}
async function publish(page: Page, workspace: string) {
    await page
        .getByRole("link", { name: "Review & Publish", exact: true })
        .click();
    await page.getByLabel(/I reviewed this configuration/).check();
    await page
        .getByRole("button", { name: /^Publish (Package|Changes)$/ })
        .click();
    await page
        .getByRole("alertdialog")
        .getByRole("button", { name: "Confirm Publication", exact: true })
        .click();
    await expect(page).toHaveURL(workspace);
}
test("service-owned combined packs preserve defaults, publish revisions and clone historical presentation", async ({
    page,
}) => {
    test.setTimeout(180000);
    await login(page, run, password);
    await page.goto("/admin/add-on-services");
    await page
        .getByRole("button", { name: "Create Add-on Service", exact: true })
        .click();
    await page.getByLabel("Service name").fill("Flexible presentation service");
    await configureService(page, "combined_contents", "Meal Pack");
    await reviewResponsive(page, "service-owned-configuration");
    await page
        .getByRole("button", { name: "Create Service", exact: true })
        .click();
    await page
        .getByRole("link", { name: "Open newly created service" })
        .click();
    await expect(page).toHaveURL(/\/admin\/add-on-services\/\d+$/);
    const serviceWorkspace = page.url();
    await createPackage(page, "Combined Meal");
    for (const name of ["Format Rice", "Format Drink", "Format Roast"])
        await inlineFood(page, name, "Format Foods");
    await page.getByLabel("Total included food selections").fill("3");
    for (const name of ["Format Rice", "Format Drink", "Format Roast"])
        await page
            .getByLabel(`Always include ${name}`, { exact: true })
            .check();
    await step(page, "Details");
    await expect(page.getByLabel("What does the customer receive")).toHaveCount(
        0,
    );
    await expect(
        page.getByRole("region", { name: "Inherited service presentation" }),
    ).toContainText("Meal Pack");
    await expect(page.getByLabel("Package Name", { exact: true })).toHaveValue(
        "Combined Meal",
    );
    await reviewResponsive(page, "combined-presentation-details");
    await step(page, "Menu");
    await expect(
        page.getByText("Optional: Default Food Portion", {
            exact: true,
        }),
    ).toBeVisible();
    await expect(
        page.getByLabel("Set a default food portion"),
    ).not.toBeVisible();
    await expect(
        page.getByLabel("Always include Format Rice", { exact: true }),
    ).toBeChecked();
    await step(page, "Pricing & Quantity");
    await page.getByLabel("Price per complete Meal Pack (PHP)").fill("149");
    await page.getByLabel("Minimum complete Meal Pack units").fill("10");
    await expect(page.getByLabel("Meal selections")).toHaveCount(0);
    await step(page, "Review & Save");
    await expect(
        page.getByText(/configured combination of 3 distinct foods/),
    ).toBeVisible();
    await savePackage(page);
    const workspace = page.url();
    await publish(page, workspace);
    const original = (await persisted(page, workspace)).history[0]
        .published_snapshot;
    expect(original.presentation.format).toBe("combined_contents");
    expect(original.presentation.default.quantity).toBe(1);
    expect(original.presentation.default.measurement_unit.name).toBe(
        "Meal Pack",
    );
    expect(original.version.minimum_quantity).toBe(10);
    expect(original.version.base_price_minor).toBe("14900");
    expect(original.menu.food_items).toHaveLength(3);
    expect(
        original.menu.food_items.every(
            (food: { is_fixed: boolean }) => food.is_fixed,
        ),
    ).toBe(true);
    await page
        .getByRole("button", { name: "Edit Package", exact: true })
        .click();
    await expect(page.getByLabel("What does the customer receive")).toHaveCount(
        0,
    );
    await step(page, "Pricing & Quantity");
    await page.getByLabel("Price per complete Meal Pack (PHP)").fill("159");
    await savePackage(page);
    await publish(page, workspace);
    const revised = await persisted(page, workspace);
    expect(revised.history[0].published_snapshot).toEqual(original);
    expect(revised.history[1].published_snapshot.presentation.format).toBe(
        "combined_contents",
    );
    expect(revised.history[1].published_snapshot.version.base_price_minor).toBe(
        "15900",
    );
    expect(
        revised.commercialHistory.publications[1].changes.length,
    ).toBeGreaterThan(0);
    const old = page
        .locator("#history details")
        .filter({
            has: page.locator("summary", { hasText: "Version 1 · Published" }),
        })
        .first();
    await old.locator("summary").first().click();
    await old
        .getByRole("button", {
            name: "Create New Draft from Version 1",
            exact: true,
        })
        .click();
    await expect(
        page.getByRole("heading", { name: "Saved Draft", exact: true }),
    ).toBeVisible();
    await page
        .getByRole("button", { name: "Edit Package", exact: true })
        .click();
    await expect(page.getByLabel("What does the customer receive")).toHaveCount(
        0,
    );
    await expect(
        page.getByRole("region", { name: "Inherited service presentation" }),
    ).toContainText("Meal Pack");
    expect(
        (await persisted(page, workspace)).history[0].published_snapshot,
    ).toEqual(original);
    await page.keyboard.press("Escape");
    await page.goto(serviceWorkspace);
    await page.getByRole("button", { name: "Edit", exact: true }).click();
    await expect(page.getByLabel("How are foods provided?")).toBeDisabled();
    await configureService(page, "combined_contents", "Meal Box");
    await page
        .getByRole("button", { name: "Save changes", exact: true })
        .click();
    await expect(page.getByRole("dialog")).not.toBeVisible();
    await createPackage(page, "Two Food Box");
    await page.getByLabel("Total included food selections").fill("2");
    for (const food of ["Format Rice", "Format Drink"]) {
        await page.getByLabel(food, { exact: true }).check();
        await page
            .getByLabel(`Always include ${food}`, { exact: true })
            .check();
    }
    await step(page, "Pricing & Quantity");
    await page.getByLabel("Price per complete Meal Box (PHP)").fill("160");
    await savePackage(page);
    const secondWorkspace = page.url();
    await publish(page, secondWorkspace);
    const second = (await persisted(page, secondWorkspace)).history[0]
        .published_snapshot;
    expect(second.presentation.default.measurement_unit.name).toBe("Meal Box");
    expect(second.menu.food_items).toHaveLength(2);
    await page.goto(workspace);
    expect(
        (await persisted(page, workspace)).history[0].published_snapshot,
    ).toEqual(original);
    await page
        .getByRole("button", { name: "Edit Package", exact: true })
        .click();
    await expect(
        page.getByRole("region", { name: "Inherited service presentation" }),
    ).toContainText("Meal Pack");
});
