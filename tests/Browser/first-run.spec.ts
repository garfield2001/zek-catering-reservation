import { test, expect } from "@playwright/test";
import { randomBytes } from "node:crypto";
import { execFileSync } from "node:child_process";

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

test("Admin starts from an empty catalog and creates Main Catering", async ({
    page,
}) => {
    await page.goto("/login");
    await page.getByLabel("Email address").fill(`admin-${run}@example.test`);
    await page.getByLabel("Password", { exact: true }).fill(password);
    await page.getByRole("button", { name: "Sign in" }).click();
    await expect(
        page.getByText("No commercial products are preloaded.", {
            exact: false,
        }),
    ).toBeVisible();
    await page.getByRole("link", { name: "Open collections" }).click();
    await expect(
        page.getByRole("heading", { name: "No collections yet" }),
    ).toBeVisible();
    await page.getByRole("button", { name: "Start setup" }).click();
    const dialog = page.getByRole("dialog", { name: "Create collection" });
    await expect(dialog.getByLabel("Collection role")).toHaveValue("primary");
    await dialog.getByLabel("Customer-facing name").fill("Main Catering");
    await dialog.getByRole("button", { name: "Create collection" }).click();
    await expect(
        page.getByRole("heading", { name: "Main Catering" }),
    ).toBeVisible();
    await expect(
        page.getByText("Main catering packages", { exact: true }),
    ).toBeVisible();
});
