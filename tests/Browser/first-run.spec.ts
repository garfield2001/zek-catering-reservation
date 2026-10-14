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

test("fresh install provisions catering without manual Collection setup", async ({
    page,
}) => {
    await login(page, run, password);
    await page
        .getByRole("navigation", { name: "Administration" })
        .getByRole("link", { name: "Catering Packages", exact: true })
        .click();
    await expect(
        page.getByRole("heading", { name: "Catering Packages", exact: true }),
    ).toBeVisible();
    await expect(page.getByText("No packages yet")).toBeVisible();
    await createPackage(page, "Independent Package");
    await expect(page.getByLabel("Add-on service")).toHaveCount(0);
    expect(new URL(page.url()).pathname).toBe("/admin/packages/editor");

    await expect(
        page.getByRole("button", {
            name: "Add Food or New Category",
            exact: true,
        }),
    ).toBeVisible();
    await expect(
        page
            .getByRole("navigation", { name: "Administration" })
            .locator('[aria-current="page"]'),
    ).toHaveCount(1);
    await page.screenshot({
        path: "test-results/first-package-workspace.png",
        fullPage: true,
    });
});
