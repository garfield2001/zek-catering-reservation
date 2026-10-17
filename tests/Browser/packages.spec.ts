import { test, expect } from "@playwright/test";
import { randomBytes } from "node:crypto";
import { execFileSync } from "node:child_process";
import {
    login,
    createPackage,
    step,
    inlineFood,
    savePackage,
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

test("canonical package navigation does not mix primary and add-on packages", async ({
    page,
}) => {
    await login(page, run, password);
    await page.goto("/admin/catering-packages");
    await createPackage(page, "Main Only");
    await inlineFood(page, "Scope Food", "Scope Foods");
    await page.getByLabel("Scope Food", { exact: true }).check();
    await step(page, "Pricing & Quantity");
    await page.getByLabel("Price per pax (PHP)").fill("500.00");
    await savePackage(page);
    await page.goto("/admin/add-on-services");
    await page.getByRole("button", { name: "Create Add-on Service" }).click();
    await page.getByLabel("Service name").fill("Party Sets");
    await page.getByRole("radio", { name: /Sold by Package/ }).check();
    await page
        .getByRole("button", { name: "Create Service", exact: true })
        .click();
    await page
        .getByRole("link", { name: "Open newly created service" })
        .click();
    await createPackage(page, "Bundle Only");
    await inlineFood(page, "Bundle Food", "Scope Foods");
    await page.getByLabel("Bundle Food", { exact: true }).check();
    await step(page, "Pricing & Quantity");
    await page.getByLabel("Price per package (PHP)").fill("4500.00");
    await savePackage(page);
    await page.goto("/admin/packages/products?scope=primary");
    await expect(page).toHaveURL(/\/admin\/catering-packages$/);
    await expect(
        page.getByRole("heading", { name: "Main Only" }),
    ).toBeVisible();
    await expect(page.getByText("Bundle Only")).toHaveCount(0);
    await page.goto("/admin/packages/products?scope=addon");
    await expect(page).toHaveURL(/\/admin\/add-on-services$/);
    await page
        .getByRole("article")
        .filter({ has: page.getByRole("heading", { name: "Party Sets" }) })
        .getByRole("link", { name: "Open Service" })
        .click();
    await expect(
        page.getByRole("heading", { name: "Bundle Only" }),
    ).toBeVisible();
    await expect(page.getByText("Main Only")).toHaveCount(0);
});

test("unprivileged staff cannot reach catalog or package mutation workflows", async ({
    page,
}) => {
    await login(page, run, password, "staff");
    await expect(
        page
            .getByRole("navigation", { name: "Administration" })
            .getByRole("link", { name: "Food Catalog" }),
    ).toHaveCount(0);
    for (const path of [
        "/admin/food-catalog",
        "/admin/catering-packages/create",
        "/admin/add-on-services",
    ])
        expect((await page.goto(path))?.status()).toBe(403);
});
