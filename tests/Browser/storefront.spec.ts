import { expect, test } from "@playwright/test";
import { execFileSync } from "node:child_process";
import { randomBytes } from "node:crypto";
import { login } from "./workflow";

const run = randomBytes(6).toString("hex");
const password = `Browser-${randomBytes(18).toString("hex")}A1`;

// This remains one serial end-to-end business journey: it covers the public
// inquiry, explicit Staff draft save, issuance, receipt-driven acceptance, and verification.
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

test("public storefront keeps internal authentication separate and submits an inquiry-only request", async ({
    page,
    context,
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
    await expect(
        page.getByRole("tab", { name: /Browser Rice: 0\/2/ }),
    ).toBeVisible();
    await expect(page.getByRole("status")).toContainText(
        /Food Items selected/i,
    );
    expect(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= window.innerWidth,
        ),
    ).toBe(true);
    await page
        .getByRole("button", { name: "View Browser Fried Rice image" })
        .click();
    await expect(
        page.getByRole("dialog", { name: "Preview Browser Fried Rice" }),
    ).toBeVisible();
    await page.getByRole("button", { name: "Zoom in" }).click();
    await expect(page.getByText("150%", { exact: true })).toBeVisible();
    await page.getByRole("button", { name: "Close dialog" }).click();
    const friedRice = page.locator("article").filter({
        has: page.getByRole("heading", {
            name: "Browser Fried Rice",
            exact: true,
        }),
    });
    await friedRice.getByRole("button", { name: "Select food" }).click();
    await expect(
        page.getByRole("tab", { name: /Browser Rice: 1\/2/ }),
    ).toBeVisible();
    await page.getByRole("tab", { name: /Browser Chicken:/ }).click();
    const chicken = page.locator("article").filter({
        has: page.getByRole("heading", {
            name: "Browser Chicken",
            exact: true,
        }),
    });
    await chicken.getByRole("button", { name: "Select food" }).click();
    await page.getByRole("button", { name: "Update estimate" }).click();
    await expect(page.getByText("Current planning estimate")).toBeVisible();
    await page
        .getByRole("button", { name: "Save Main Catering choice" })
        .click();
    await expect(
        page.getByRole("status").filter({
            hasText: "Main Catering package is saved",
        }),
    ).toBeVisible();
    await expect(
        page.getByRole("link", {
            name: /Review inquiry selection/i,
        }),
    ).toHaveCount(0);
    await page.getByRole("link", { name: "Continue to event details" }).click();
    await page.waitForURL(/\/my-selection$/);
    await expect(page.getByText("Browser Celebration Package")).toBeVisible();
    await page
        .getByRole("link", { name: "Continue to event details", exact: true })
        .click();
    await page.waitForURL(/\/reservation-request$/);
    await expect(
        page.getByRole("heading", { name: "Request Staff review" }),
    ).toBeVisible();
    await page.getByLabel("Full name").fill("Browser Inquiry");
    await page.getByLabel("Mobile number").fill("09171234567");
    await page.getByLabel("Email address").fill("inquiry@example.test");
    await page
        .getByLabel("Event date")
        .fill(
            new Date(Date.now() + 20 * 24 * 60 * 60 * 1000)
                .toISOString()
                .slice(0, 10),
        );
    await page.getByLabel("Start time").fill("10:00");
    await page.getByLabel("Tentative venue information").fill("Browser venue");
    await page.getByRole("button", { name: "Submit request" }).click();
    await expect(
        page.getByRole("heading", { name: "Thank you for your inquiry" }),
    ).toBeVisible();
    const reference = await page.locator("output").first().innerText();
    expect(reference).toMatch(/^ZEK-[A-Z0-9]+$/);
    const accessCode = await page.locator("output").nth(1).innerText();
    expect(accessCode).toMatch(/^[A-Z0-9]+$/);
    await context.grantPermissions(["clipboard-read", "clipboard-write"], {
        origin: new URL(page.url()).origin,
    });
    await page.getByRole("button", { name: "Copy Reference Code" }).click();
    await expect(page.getByRole("button", { name: "Copied!" })).toBeVisible();
    await expect(
        page.getByRole("status").filter({
            hasText: "Reference code copied.",
        }),
    ).toBeVisible();
    await expect(
        page.evaluate(() => navigator.clipboard.readText()),
    ).resolves.toBe(reference);

    await page.goto("/login");
    await expect(page.getByLabel("Email address")).toBeVisible();
    await login(page, run, password);
    await page.goto("/");
    await expect(
        page.getByRole("heading", {
            name: "Food made for the moments that bring people together.",
        }),
    ).toBeVisible();
    await page.goto("/admin/reservation-requests");
    await expect(
        page.getByRole("heading", { name: "Reservation Requests" }),
    ).toBeVisible();
    await page.getByRole("link", { name: reference, exact: true }).click();
    await expect(
        page.getByText("Browser Inquiry", { exact: true }),
    ).toBeVisible();
    await page.getByRole("button", { name: "Mark Under Review" }).click();
    await expect(page.getByText(/^Current:/)).toContainText("under review");
    const requestUrl = page.url();
    await page.getByRole("link", { name: "Prepare quotation" }).click();
    await expect(
        page.getByRole("heading", { name: "Prepare Quotation" }),
    ).toBeVisible();
    await page.getByRole("button", { name: "Save quotation draft" }).click();
    await page.waitForURL(/\/admin\/quotations\/\d+$/);
    await expect(page.getByText("Current commercial version")).toBeVisible();
    await expect(
        page.getByRole("navigation", { name: "Reservation case progress" }),
    ).toContainText("Available after the customer accepts this quotation.");
    const quotationUrl = page.url();
    await page.goto(requestUrl);
    await expect(
        page.getByRole("link", { name: "Continue quotation draft" }),
    ).toBeVisible();
    await page.getByRole("link", { name: "Continue quotation draft" }).click();
    await expect(page).toHaveURL(quotationUrl);
    await page.goto("/admin/business-policies");
    await expect(
        page.getByRole("heading", { name: "Business Policies" }),
    ).toBeVisible();
    await page.goto("/admin/quotations");
    await page.getByRole("link", { name: /^QT-/ }).click();
    const validity = new Date(Date.now() + 24 * 60 * 60 * 1000)
        .toISOString()
        .slice(0, 16);
    await page.getByLabel("Valid until (optional override)").fill(validity);
    await page.getByRole("button", { name: "Issue quotation" }).click();
    await expect(page.getByText(/Track Your Request/i)).toBeVisible();
    await page.goto("/track-request");
    await page.getByLabel("Request reference").fill(reference);
    await page.getByLabel("Registered mobile number").fill("09171234567");
    await page.getByLabel("Customer access code").fill(accessCode);
    await page.getByRole("button", { name: "View my request" }).click();
    await expect(page).toHaveURL(new RegExp(`/track-request/${reference}$`));
    await expect(page.getByText("Browser Celebration Package")).toBeVisible();
    await expect(
        page.getByText("Quotation available", { exact: true }),
    ).toBeVisible();
    await expect(
        page.getByText("Reservation confirmation", { exact: true }),
    ).toBeVisible();
    await expect(
        page.getByRole("button", { name: "Print or save as PDF" }),
    ).toBeVisible();
    await page.getByLabel("Receipt image (JPEG, PNG, or WebP)").setInputFiles({
        name: "receipt.png",
        mimeType: "image/png",
        buffer: Buffer.from(
            "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL2rwAAAABJRU5ErkJggg==",
            "base64",
        ),
    });
    await page
        .getByRole("button", { name: "Accept quotation and upload receipt" })
        .click();
    await expect(
        page.getByText(/Receipt submitted for Administrator verification/i),
    ).toBeVisible();
    await expect(
        page.getByText("Quotation accepted through receipt submission"),
    ).toBeVisible();
    await expect(
        page.getByText("Receipt submitted", { exact: true }),
    ).toBeVisible();

    await page.goto("/admin/quotations");
    await expect(page.getByRole("cell", { name: "accepted" })).toBeVisible();
    await page.goto("/admin/payments");
    await expect(page.getByText("pending verification")).toBeVisible();
    await page.getByLabel("Amount received (PHP)").fill("1000.00");
    await page.getByRole("button", { name: "Verify" }).click();
    await expect(page.getByText("verified")).toBeVisible();
    await page.goto(quotationUrl);
    const quotationProgress = page.getByRole("navigation", {
        name: "Reservation case progress",
    });
    await expect(quotationProgress).toContainText(
        "Verified reservation payment still required.",
    );
    await expect(quotationProgress).toContainText(
        "Confirmation remains uncommitted until payment and capacity checks pass.",
    );
    await expect(
        page.getByRole("link", { name: "Preview receipt v1" }),
    ).toHaveAttribute(
        "href",
        /\/admin\/payments\/\d+\/evidence\/\d+\/preview$/,
    );
    await page.getByRole("button", { name: "Account menu" }).click();
    await page.getByRole("menuitem", { name: "Sign out" }).click();
    await expect(page).toHaveURL(/\/login$/);

    // A verified initial receipt does not close the customer upload window.
    // The customer can add a distinct second receipt; both histories and the
    // aggregate verified balance remain visible through Track Your Request.
    await page.goto("/track-request");
    await page.getByLabel("Request reference").fill(reference);
    await page.getByLabel("Registered mobile number").fill("09171234567");
    await page.getByLabel("Customer access code").fill(accessCode);
    await page.getByRole("button", { name: "View my request" }).click();
    await page.getByLabel("Receipt image (JPEG, PNG, or WebP)").setInputFiles({
        name: "receipt-two.png",
        mimeType: "image/png",
        // A separate valid PNG is required: appending arbitrary bytes to the
        // first image creates malformed upload data rather than distinct
        // customer evidence.
        buffer: Buffer.from(
            "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9J36AAAAAASUVORK5CYII=",
            "base64",
        ),
    });
    await page
        .getByRole("button", { name: "Upload additional receipt" })
        .click();
    await expect(
        page.getByText(/Receipt submitted for Administrator verification/i),
    ).toBeVisible();
    await expect(page.getByText("pending verification")).toBeVisible();

    await page.goto("/login");
    await login(page, run, password);
    await page.goto("/admin/payments");
    await page.getByLabel("Amount received (PHP)").fill("1000.00");
    await page.getByRole("button", { name: "Verify" }).click();
    await expect(page.getByText("verified")).toHaveCount(2);
    await expect(page.getByRole("link", { name: "Preview v1" })).toHaveCount(2);

    await page.getByRole("button", { name: "Account menu" }).click();
    await page.getByRole("menuitem", { name: "Sign out" }).click();
    await page.goto("/track-request");
    await page.getByLabel("Request reference").fill(reference);
    await page.getByLabel("Registered mobile number").fill("09171234567");
    await page.getByLabel("Customer access code").fill(accessCode);
    await page.getByRole("button", { name: "View my request" }).click();
    await expect(page.getByText(/Verified:\s*₱2,000\.00/)).toBeVisible();
    await expect(
        page.getByText("Partial payment verified", { exact: true }),
    ).toBeVisible();
    await expect(
        page.getByText("Reservation confirmation", { exact: true }),
    ).toBeVisible();
    const paymentHistory = page
        .getByRole("heading", { name: "Payments" })
        .locator("..")
        .getByRole("listitem");
    await expect(paymentHistory).toHaveCount(2);
    await expect(paymentHistory).toContainText(["verified", "verified"]);

    await expect(
        page.getByRole("banner").getByRole("link", { name: "ZEK CATERING" }),
    ).toHaveAttribute("href", "/");
});
