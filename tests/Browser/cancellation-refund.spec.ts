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

test("Administrator finalizes a cancellation and records an approved manual refund", async ({
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

    await page
        .getByLabel("Cancellation reason", { exact: true })
        .fill("Customer asked to cancel the event.");
    await page
        .getByRole("button", {
            name: "Submit cancellation for review",
            exact: true,
        })
        .click();
    await expect(
        page.getByText("pending review", { exact: true }),
    ).toBeVisible();
    await page.getByLabel(/Finalize this cancellation/).check();
    await page
        .getByLabel("Administrator decision and reason", { exact: true })
        .fill("Cancellation approved after an operational review.");
    await page
        .getByRole("button", {
            name: "Save Administrator decision",
            exact: true,
        })
        .click();
    await expect(
        page.getByText("refund under review", { exact: true }),
    ).toBeVisible();

    await page.getByLabel("Amount (PHP)", { exact: true }).fill("250");
    await page
        .getByLabel("Refund reason", { exact: true })
        .fill("Partial manual refund approved by management.");
    await page
        .getByRole("button", {
            name: "Submit refund for review",
            exact: true,
        })
        .click();
    await page.getByLabel("Approve this refund").check();
    await page
        .getByRole("button", { name: "Save refund decision", exact: true })
        .click();
    await expect(page.getByText(/Refund #1.*approved/)).toBeVisible();
    await page
        .getByRole("button", {
            name: "Record completed refund",
            exact: true,
        })
        .click();
    await page
        .getByRole("alertdialog")
        .getByRole("button", {
            name: "Record completed refund",
            exact: true,
        })
        .click();
    await expect(page.getByText(/Refund #1.*completed/)).toBeVisible();
});
