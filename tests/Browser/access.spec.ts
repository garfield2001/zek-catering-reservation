import { test, expect } from "@playwright/test";
import { randomBytes } from "node:crypto";
import { execFileSync } from "node:child_process";
const run = randomBytes(6).toString("hex");
const password = "Browser-" + randomBytes(18).toString("hex") + "A1";
test.beforeAll(() => {
    execFileSync("php", ["tests/Browser/seed.php"], {
        env: { ...process.env, BROWSER_RUN: run, BROWSER_PASSWORD: password },
    });
});
test("Admin can grant Staff audit access through the interface", async ({
    page,
}) => {
    await page.goto("/login");
    await page
        .getByLabel("Email address")
        .fill("admin-" + run + "@example.test");
    await page.getByLabel("Password", { exact: true }).fill(password);
    await page.getByRole("button", { name: "Sign in" }).click();
    await expect(page.getByRole("heading", { name: "Overview" })).toBeVisible();
    await page
        .getByRole("link", { name: "Team & access", exact: true })
        .click();
    const row = page
        .getByRole("row")
        .filter({ hasText: "staff-" + run + "@example.test" });
    await row.getByRole("link", { name: "Permissions" }).click();
    await page
        .getByLabel("View audit history", { exact: false })
        .selectOption("allow");
    await page.getByRole("button", { name: "Save permissions" }).click();
    await expect(page.getByRole("status")).toContainText("Permissions updated");
    await page.getByRole("button", { name: "Sign out" }).click();
    await page
        .getByLabel("Email address")
        .fill("staff-" + run + "@example.test");
    await page.getByLabel("Password", { exact: true }).fill(password);
    await page.getByRole("button", { name: "Sign in" }).click();
    await page
        .getByRole("link", { name: "Activity History", exact: true })
        .click();
    await expect(
        page.getByText("Permissions updated", { exact: true }).first(),
    ).toBeVisible();
    const response = await page.goto("/admin/staff");
    expect(response?.status()).toBe(403);
});
test("real HTTP login requires a CSRF token and registration is absent", async ({
    request,
}) => {
    const response = await request.post("/login", {
        form: { email: "admin-" + run + "@example.test", password },
    });
    expect(response.status()).toBe(419);
    expect((await request.get("/register")).status()).toBe(404);
});
