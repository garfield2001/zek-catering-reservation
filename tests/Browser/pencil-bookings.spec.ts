import { expect, test } from "@playwright/test";
import { execFileSync } from "node:child_process";
import { randomBytes } from "node:crypto";
import { login } from "./workflow";

const run = randomBytes(6).toString("hex");
const password = `Browser-${randomBytes(18).toString("hex")}A1`;

test.setTimeout(90_000);

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

async function configureMainPackage(page: import("@playwright/test").Page) {
    const packageDetails = page
        .locator("details")
        .filter({ hasText: "Browser Celebration Package" })
        .first();
    if (!(await packageDetails.evaluate((element) => element.open))) {
        await packageDetails.locator("summary").click();
    }
    const friedRice = page.locator("article").filter({
        has: page.getByRole("heading", {
            name: "Browser Fried Rice",
            exact: true,
        }),
    });
    await friedRice.getByRole("button", { name: "Select food" }).click();
    await page.getByRole("tab", { name: /Browser Chicken:/ }).click();
    const chicken = page.locator("article").filter({
        has: page.getByRole("heading", {
            name: "Browser Chicken",
            exact: true,
        }),
    });
    await chicken.getByRole("button", { name: "Select food" }).click();
    await page
        .getByRole("button", { name: "Use Browser Celebration Package" })
        .click();
}

test("Administrator creates an internal request and converts a pencil booking without using the customer selection session", async ({
    page,
}) => {
    await login(page, run, password);
    await page.goto("/admin/reservation-requests/create");
    await expect(
        page.getByRole("heading", { name: "Create reservation request" }),
    ).toBeVisible();
    await page.getByLabel("Customer name").fill("Internal Browser Customer");
    await page.getByLabel("Mobile number").fill("09171234567");
    await page
        .getByLabel("Event date")
        .fill(
            new Date(Date.now() + 20 * 24 * 60 * 60 * 1000)
                .toISOString()
                .slice(0, 10),
        );
    await page.getByLabel("Event start time").fill("10:00");
    await page.getByLabel("Venue").fill("Internal browser venue");
    await configureMainPackage(page);
    await expect(
        page.getByText("Browser Celebration Package", { exact: true }).last(),
    ).toBeVisible();
    await page
        .getByRole("button", { name: "Create reservation request" })
        .click();
    await expect(page).toHaveURL(/\/admin\/reservation-requests\/\d+$/);
    await expect(
        page.getByText("Internal request", { exact: true }),
    ).toBeVisible();

    await page.goto("/admin/pencil-bookings");
    await page.getByLabel("Customer name").fill("Pencil Browser Customer");
    await page.getByLabel("Mobile number").fill("09179876543");
    await page
        .getByLabel("Tentative event date")
        .fill(
            new Date(Date.now() + 21 * 24 * 60 * 60 * 1000)
                .toISOString()
                .slice(0, 10),
        );
    await page.getByRole("button", { name: "Save pencil booking" }).click();
    await page
        .getByRole("link", { name: "Pencil Browser Customer", exact: true })
        .click();
    await page.getByLabel("Event start time").first().fill("11:00");
    await page.getByLabel("Venue").first().fill("Pencil browser venue");
    await page.getByRole("button", { name: "Save changes" }).click();
    await expect(
        page.getByRole("heading", { name: "Convert to reservation request" }),
    ).toBeVisible();
    await configureMainPackage(page);
    await page
        .getByRole("button", { name: "Create reservation request" })
        .click();
    await expect(page).toHaveURL(/\/admin\/reservation-requests\/\d+$/);
    await expect(
        page.getByText("Pencil booking conversion", { exact: true }),
    ).toBeVisible();
});
