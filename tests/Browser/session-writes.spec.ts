import { configureService, customizeFood, openPortions } from "./workflow";
import { test, expect, type Page } from "@playwright/test";
import { randomBytes } from "node:crypto";
import { execFileSync } from "node:child_process";
import { login, createPackage, inlineFood, step } from "./workflow";

const run = randomBytes(6).toString("hex");
const password = "Browser-" + randomBytes(18).toString("hex") + "A1";
const count = (table: string, name: string) =>
    Number(
        execFileSync(
            "php",
            ["tests/Browser/catalog-record-count.php", table, name],
            { encoding: "utf8" },
        ),
    );
const navigation = (page: Page, name: string) =>
    page
        .getByRole("navigation", { name: "Administration", exact: true })
        .getByRole("link", { name, exact: true })
        .click();
async function openFood(page: Page, name: string) {
    await page
        .getByRole("button", { name: "Add Food Item", exact: true })
        .click();
    await page.getByLabel("Food item name").fill(name);
    await page.getByLabel("Food category", { exact: true }).selectOption("new");
    await page.getByLabel("New category name").fill(`${name} Category`);
}
async function submitFood(page: Page, button = "Create food item") {
    const saved = page.waitForResponse(
        (response) =>
            response.request().method() === "POST" &&
            new URL(response.url()).pathname === "/admin/catalog/dishes",
    );
    await page.getByRole("button", { name: button, exact: true }).click();
    return saved;
}
// Authentication evidence must never retain cookie/header/credential values in traces.
test.use({ trace: "off" });
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

test("current session cookie authenticates while a stale raw CSRF header is rejected", async ({
    page,
}) => {
    await page.goto("/login");
    const stale = (await page.locator('meta[name="csrf-token"]').count())
        ? await page.locator('meta[name="csrf-token"]').getAttribute("content")
        : "invalid-token";
    await page.getByLabel("Email address").fill(`admin-${run}@example.test`);
    await page.getByLabel("Password", { exact: true }).fill(password);
    await page.getByRole("button", { name: "Sign in" }).click();
    await expect(page).toHaveURL(/\/admin(?:\/home)?$/);
    const cookies = await page.context().cookies();
    const current = cookies.find((cookie) => cookie.name === "XSRF-TOKEN");
    expect(Boolean(current)).toBe(true);
    expect((await page.request.get("/admin/home")).status()).toBe(200);
    const headers = {
        Accept: "application/json",
        "X-XSRF-TOKEN": decodeURIComponent(current!.value),
    };
    const rejected = await page.request.post("/admin/add-on-services", {
        headers: { ...headers, "X-CSRF-TOKEN": stale ?? "invalid" },
        data: {
            name: "Rejected CSRF Service",
            kind: "addon",
            behavior_type: "package",
            active: true,
        },
    });
    expect(rejected.status()).toBe(419);
    expect(count("product_families", "Rejected CSRF Service")).toBe(0);
    const validated = await page.request.post("/admin/add-on-services", {
        headers,
        data: {},
    });
    expect(validated.status()).toBe(422);
});

test("fresh login saves a Food Item through Inertia navigation without reloading", async ({
    page,
}) => {
    await login(page, run, password);
    await page
        .getByRole("navigation", { name: "Administration", exact: true })
        .getByRole("link", { name: "Food Catalog", exact: true })
        .click();
    await page
        .getByRole("button", { name: "Add Food Item", exact: true })
        .click();
    await page.getByLabel("Food item name").fill("Immediate Login Food");
    await page.getByLabel("Food category", { exact: true }).selectOption("new");
    await page.getByLabel("New category name").fill("Immediate Foods");
    const saved = page.waitForResponse(
        (response) =>
            response.request().method() === "POST" &&
            new URL(response.url()).pathname === "/admin/catalog/dishes",
    );
    await page
        .getByRole("button", { name: "Create food item", exact: true })
        .click();
    expect((await saved).status()).toBe(201);
    await expect(
        page.getByRole("heading", {
            name: "Immediate Login Food",
            exact: true,
        }),
    ).toBeVisible();
    expect(count("dishes", "Immediate Login Food")).toBe(1);
});

test("fresh login creates an Add-on Service once without a document reload", async ({
    page,
}) => {
    await login(page, run, password);
    let documents = 0;
    page.on("request", (request) => {
        if (request.resourceType() === "document") documents++;
    });
    await navigation(page, "Add-on Services");
    await page
        .getByRole("button", { name: "Create Add-on Service", exact: true })
        .click();
    await page.getByLabel("Service name").fill("Immediate Service");
    await configureService(page);
    const saved = page.waitForResponse(
        (response) =>
            response.request().method() === "POST" &&
            new URL(response.url()).pathname === "/admin/add-on-services",
    );
    await page
        .getByRole("button", { name: "Create Service", exact: true })
        .click();
    expect((await saved).status()).toBe(201);
    await expect(
        page.getByRole("link", { name: "Open newly created service" }),
    ).toBeVisible();
    expect(count("product_families", "Immediate Service")).toBe(1);
    expect(documents).toBe(0);
});

test("consecutive multipart saves retain category and survive logout login and reload", async ({
    page,
}) => {
    test.setTimeout(90000);
    await login(page, run, password);
    await navigation(page, "Food Catalog");
    await openFood(page, "Repeated Food 1");
    const image = execFileSync("php", [
        "-r",
        "$image = imagecreatetruecolor(32, 32); imagepng($image); imagedestroy($image);",
    ]);
    await page.getByRole("dialog").locator("input[type=file]").setInputFiles({
        name: "food.png",
        mimeType: "image/png",
        buffer: image,
    });
    expect((await submitFood(page, "Save & Add Another")).status()).toBe(201);
    await expect(page.getByLabel("Food item name")).toHaveValue("");
    await expect(
        page
            .getByLabel("Food category", { exact: true })
            .locator("option:checked"),
    ).toHaveText("Repeated Food 1 Category");
    await expect(
        page.getByRole("dialog").locator("input[type=file]"),
    ).toHaveValue("");
    for (const name of ["Repeated Food 2", "Repeated Food 3"]) {
        await page.getByLabel("Food item name").fill(name);
        expect((await submitFood(page, "Save & Add Another")).status()).toBe(
            201,
        );
        await expect(page.getByLabel("Food item name")).toHaveValue("");
        expect(count("dishes", name)).toBe(1);
    }
    expect(count("dishes", "Repeated Food 1")).toBe(1);
    await page.keyboard.press("Escape");
    await page.getByRole("button", { name: "Account menu" }).click();
    await page.getByRole("menuitem", { name: "Sign out" }).click();
    await expect(page).toHaveURL(/\/login$/);
    // Login from the existing Inertia login page, deliberately no goto/reload.
    await page.getByLabel("Email address").fill(`admin-${run}@example.test`);
    await page.getByLabel("Password", { exact: true }).fill(password);
    await page.getByRole("button", { name: "Sign in" }).click();
    await expect(page).toHaveURL(/\/admin(?:\/home)?$/);
    await navigation(page, "Food Catalog");
    await openFood(page, "Relogin Food");
    expect((await submitFood(page)).status()).toBe(201);
    await expect(page.getByRole("dialog")).not.toBeVisible();
    await page.reload();
    await openFood(page, "Reload Food");
    expect((await submitFood(page)).status()).toBe(201);
    expect(count("dishes", "Relogin Food")).toBe(1);
    expect(count("dishes", "Reload Food")).toBe(1);
});

test("inline food and unit plus package save work after login without losing parent input", async ({
    page,
}) => {
    test.setTimeout(90000);
    await login(page, run, password);
    await navigation(page, "Add-on Services");
    await page
        .getByRole("button", { name: "Create Add-on Service", exact: true })
        .click();
    await page.getByLabel("Service name").fill("Inline Session Service");
    await configureService(page);
    await page
        .getByRole("button", { name: "Create Service", exact: true })
        .click();
    await page
        .getByRole("link", { name: "Open newly created service" })
        .click();
    await createPackage(page, "Inline Session Package");
    await inlineFood(page, "Inline Session Food", "Inline Session Category");
    await page.getByLabel("Total included food selections").fill("1");
    await page
        .getByLabel("Always include Inline Session Food", { exact: true })
        .check();
    await step(page, "Details");
    await expect(page.getByLabel("Package Name", { exact: true })).toHaveValue(
        "Inline Session Package",
    );
    await step(page, "Menu");
    await openPortions(page);
    await page.getByLabel("Set a default food portion").check();
    await page
        .getByRole("button", {
            name: "Add Measurement Unit",
            exact: true,
        })
        .click();
    const unit = page.getByRole("dialog", {
        name: "Create Measurement Unit",
        exact: true,
    });
    await unit.getByLabel("Unit name").fill("Session Tray");
    await unit.getByLabel("Abbreviation").fill("session-tray");
    await unit
        .getByRole("button", { name: "Create Measurement Unit", exact: true })
        .click();
    await expect(unit).not.toBeVisible();
    await step(page, "Pricing & Quantity");
    await page.getByLabel("Price per complete package set (PHP)").fill("500");
    await step(page, "Review & Save");
    const saved = page.waitForResponse(
        (response) =>
            response.request().method() === "POST" &&
            new URL(response.url()).pathname ===
                "/admin/packages/configurations",
    );
    await page.getByRole("button", { name: "Save Draft", exact: true }).click();
    expect((await saved).ok()).toBe(true);
    await expect(page.getByRole("dialog")).not.toBeVisible();
    expect(count("dishes", "Inline Session Food")).toBe(1);
    expect(count("products", "Inline Session Package")).toBe(1);
});

test("invalid session preserves food input without retry and can recover after another-tab login", async ({
    page,
    context,
}) => {
    await login(page, run, password);
    await navigation(page, "Food Catalog");
    await openFood(page, "Expired Session Food");
    await context.clearCookies();
    let writes = 0;
    page.on("request", (request) => {
        if (
            request.method() === "POST" &&
            new URL(request.url()).pathname === "/admin/catalog/dishes"
        )
            writes++;
    });
    expect((await submitFood(page)).status()).toBe(419);
    await expect(page.getByRole("alert")).toContainText(
        "Sign in in another tab",
    );
    await expect(page.getByLabel("Food item name")).toHaveValue(
        "Expired Session Food",
    );
    expect(count("dishes", "Expired Session Food")).toBe(0);
    expect(writes).toBe(1);
    const recovery = await context.newPage();
    await login(recovery, run, password);
    await recovery.close();
    expect((await submitFood(page)).status()).toBe(201);
    await expect(page.getByRole("dialog")).not.toBeVisible();
    expect(count("dishes", "Expired Session Food")).toBe(1);
    expect(writes).toBe(2);
});

test("expired JSON service save gives a clear error and creates no record", async ({
    page,
    context,
}) => {
    await login(page, run, password);
    await navigation(page, "Add-on Services");
    await page
        .getByRole("button", { name: "Create Add-on Service", exact: true })
        .click();
    await page.getByLabel("Service name").fill("Expired Service");
    await configureService(page);
    await context.clearCookies();
    const failed = page.waitForResponse(
        (response) =>
            response.request().method() === "POST" &&
            new URL(response.url()).pathname === "/admin/add-on-services",
    );
    await page
        .getByRole("button", { name: "Create Service", exact: true })
        .click();
    expect((await failed).status()).toBe(419);
    await expect(page.getByRole("alert")).toContainText(
        "Sign in in another tab",
    );
    await expect(page.getByLabel("Service name")).toHaveValue(
        "Expired Service",
    );
    expect(count("product_families", "Expired Service")).toBe(0);
});
