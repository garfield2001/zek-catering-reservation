import { expect, test } from "@playwright/test";
import { execFileSync } from "node:child_process";
import { randomBytes } from "node:crypto";
import { login } from "./workflow";

const run = randomBytes(6).toString("hex");
const password = `Browser-${randomBytes(18).toString("hex")}A1`;

test.beforeAll(() => {
    execFileSync("php", ["tests/Browser/seed.php"], {
        env: {
            ...process.env,
            BROWSER_RUN: run,
            BROWSER_PASSWORD: password,
            BROWSER_LEGACY: "1",
            BROWSER_STOREFRONT: "1",
        },
    });
});

test("public storefront keeps internal authentication separate and retains a server-validated planning selection", async ({
    page,
}) => {
    await page.goto("/");
    await expect(
        page.getByRole("heading", {
            name: "Food made for the moments that bring people together.",
        }),
    ).toBeVisible();
    await expect(
        page.getByRole("link", { name: /login|sign in/i }),
    ).toHaveCount(0);
    await page.setViewportSize({ width: 390, height: 844 });
    await page.getByRole("button", { name: "Open navigation" }).click();
    await expect(
        page.getByRole("navigation", { name: "Customer navigation" }),
    ).not.toContainText(/login|sign in/i);
    await page.getByRole("link", { name: "Catering Packages" }).first().click();
    await page.getByRole("link", { name: "View package" }).click();
    await expect(
        page.getByRole("heading", { name: "Browser Celebration Package" }),
    ).toBeVisible();
    await page.getByRole("button", { name: "Browser Fried Rice" }).click();
    await page.getByRole("button", { name: "Browser Chicken" }).click();
    await page.getByRole("button", { name: "Update estimate" }).click();
    await expect(page.getByText("₱112,500.00")).toBeVisible();
    await page.getByRole("button", { name: "Add to My Selection" }).click();
    await page.getByRole("link", { name: /My selection, 1 items/ }).click();
    await expect(page.getByText("Browser Celebration Package")).toBeVisible();

    await page.goto("/login");
    await expect(page.getByLabel("Email address")).toBeVisible();
    await login(page, run, password);
    await page.goto("/");
    await expect(
        page.getByRole("heading", {
            name: "Food made for the moments that bring people together.",
        }),
    ).toBeVisible();
});
