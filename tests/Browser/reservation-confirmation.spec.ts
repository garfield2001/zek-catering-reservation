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
            BROWSER_LEGACY: "0",
            BROWSER_RESERVATION_CONFIRMATION: "1",
        },
    });
});

test("Administrator can open an accepted quotation's reservation confirmation review", async ({
    page,
}) => {
    await login(page, run, password);
    await page
        .getByRole("navigation", { name: "Administration", exact: true })
        .getByRole("link", { name: "Reservations", exact: true })
        .click();
    await expect(
        page.getByRole("link", { name: "Review confirmation", exact: true }),
    ).toBeVisible();
    await page
        .getByRole("link", { name: "Review confirmation", exact: true })
        .click();

    await expect(page).toHaveURL(/\/admin\/reservations\/quotations\/\d+$/);
    await expect(
        page.getByRole("heading", { name: /Review QT-/ }),
    ).toBeVisible();
    const progress = page.getByRole("navigation", {
        name: "Reservation case progress",
    });
    await expect(progress).toContainText("Accepted version");
    await expect(progress).toContainText(
        "Reservation payment requirement satisfied.",
    );
    await expect(progress).toContainText(
        "Ready for Administrator confirmation.",
    );
    await expect(
        progress.getByRole("link", { name: "Quotation", exact: true }),
    ).toHaveAttribute("href", /\/admin\/quotations\/\d+$/);
    await expect(
        page.getByText("Verified reservation-payment requirement satisfied."),
    ).toBeVisible();
    await expect(
        page.getByRole("button", { name: "Confirm reservation", exact: true }),
    ).toBeVisible();
    await page.setViewportSize({ width: 390, height: 844 });
    expect(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= window.innerWidth,
        ),
    ).toBe(true);
});

test("Administrator confirms then reviews and applies a non-commercial reservation change", async ({
    page,
}) => {
    await login(page, run, password);
    await page.goto("/admin/reservations");
    await page
        .getByRole("link", { name: "Review confirmation", exact: true })
        .click();
    await page
        .getByRole("button", { name: "Confirm reservation", exact: true })
        .click();
    await expect(
        page.getByRole("heading", { name: "Reservation changes", exact: true }),
    ).toBeVisible();
    await expect(
        page.getByRole("navigation", { name: "Reservation case progress" }),
    ).toContainText("confirmed");

    await page
        .getByLabel("Proposed venue", { exact: true })
        .fill("Updated browser venue");
    await page
        .getByRole("button", {
            name: "Submit for Administrator review",
            exact: true,
        })
        .click();
    await expect(
        page.getByText("Change request #1", { exact: true }),
    ).toBeVisible();
    await expect(
        page.getByText("pending review", { exact: true }),
    ).toBeVisible();

    await page.getByLabel("Review note (optional)").fill("Venue approved.");
    await page
        .getByRole("button", {
            name: "Save Administrator decision",
            exact: true,
        })
        .click();
    await expect(
        page.getByText("awaiting reconfirmation", { exact: true }),
    ).toBeVisible();

    await page
        .getByRole("button", {
            name: "Re-confirm and apply change",
            exact: true,
        })
        .click();
    await expect(page.getByText("applied", { exact: true })).toBeVisible();
    await expect(
        page.getByText("Updated browser venue", { exact: true }),
    ).toBeVisible();
});
