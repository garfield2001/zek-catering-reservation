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
    expect(new URL(page.url()).pathname).toMatch(
        /^\/admin\/catering-packages\/\d+\/menu$/,
    );
    await expect(
        page.getByRole("heading", {
            name: "Menu Configuration",
            exact: true,
        }),
    ).toBeVisible();

    await expect(
        page.getByRole("button", {
            name: "Add Food Item",
            exact: true,
        }),
    ).toBeVisible();
    await expect(
        page
            .getByRole("navigation", {
                name: "Administration",
                includeHidden: true,
            })
            .locator('[aria-current="page"]'),
    ).toHaveCount(1);
    await page.screenshot({
        path: "test-results/first-package-workspace.png",
        fullPage: true,
    });
});

test("main package details can be saved as a Draft before menu and pricing configuration", async ({
    page,
}) => {
    await login(page, run, password);
    await page.goto("/admin/catering-packages");
    await page
        .getByRole("link", { name: "Create Package", exact: true })
        .click();
    await page
        .getByLabel("Package Name", { exact: true })
        .fill("Saved Later Package");
    await page.getByRole("button", { name: "Save Details & Continue" }).click();

    await expect(page).toHaveURL(/\/pricing$/);
    await page.getByRole("link", { name: "Back to Package" }).click();
    await expect(
        page.getByText("Draft needs configuration", { exact: true }),
    ).toBeVisible();
    await page.getByRole("link", { name: "Complete Configuration" }).click();
    await expect(page.getByLabel("Package Name", { exact: true })).toHaveValue(
        "Saved Later Package",
    );
    await expect(
        page.getByRole("navigation", { name: "Package setup steps" }),
    ).toHaveCount(0);
});
