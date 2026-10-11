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

test("Admin can publish, revise, and archive a package product", async ({
    page,
}) => {
    test.setTimeout(120000);
    const category = `Lifecycle category ${run}`;
    const dish = `Lifecycle dish ${run}`;
    const product = `Lifecycle package ${run}`;

    await page.goto("/login");
    await page.getByLabel("Email address").fill(`admin-${run}@example.test`);
    await page.getByLabel("Password", { exact: true }).fill(password);
    await page.getByRole("button", { name: "Sign in" }).click();

    // Build the minimum real catalog graph required by a valid package draft.
    await page
        .getByRole("link", { name: "Food Categories", exact: true })
        .click();
    await page.getByRole("button", { name: "Add category" }).click();
    await page.getByLabel("Category name").fill(category);
    await page.getByRole("button", { name: "Create category" }).click();

    await page.getByRole("link", { name: "Food Items", exact: true }).click();
    await page.getByRole("link", { name: "Add food item" }).click();
    await page.getByLabel("Food item name").fill(dish);
    await page.getByLabel("Food category").selectOption({ label: category });
    await page.getByLabel("Main Catering Packages").check();
    await page.getByRole("button", { name: "Create food item" }).click();
    await expect(page.getByRole("heading", { name: dish })).toBeVisible();

    await page.getByRole("link", { name: "Add offering" }).click();
    await page
        .getByLabel("Collection")
        .selectOption({ label: "Main Catering Packages" });
    await page
        .getByLabel("Preparation name")
        .fill(`Lifecycle preparation ${run}`);
    await page.getByRole("button", { name: "Create offering" }).click();
    await page.getByRole("link", { name: "Add serving option" }).click();
    await page.getByLabel("Serving label").fill("Standard tray");
    await page.getByLabel("Size per option").fill("1.000");
    await page.getByRole("button", { name: "Create serving option" }).click();
    await expect(page.getByText("Standard tray")).toBeVisible();

    // Create the package and price its initial draft.
    await page
        .getByRole("link", { name: "Main Catering Packages", exact: true })
        .click();
    await page.getByRole("link", { name: "Create package product" }).click();
    await page
        .getByLabel("Collection")
        .selectOption({ label: "Main Catering Packages" });
    await page.getByLabel("Product name").fill(product);
    await page.getByRole("button", { name: "Create product" }).click();
    await expect(
        page.getByRole("heading", { name: /draft Version 1/i }),
    ).toBeVisible();
    await page
        .getByRole("link", { name: "Edit price and quantity rules" })
        .click();
    await page.getByLabel(/Base Price \(PHP/).fill("850.00");
    await page.getByRole("button", { name: "Save draft" }).click();

    await page.getByRole("link", { name: "Add selection group" }).click();
    await page.getByLabel("Selection group name").fill("Included main dish");
    await page.getByLabel("Inclusion type").selectOption("dish");
    await page.getByLabel("Selection mode").selectOption("fixed");
    await page
        .getByRole("radio", { name: /Lifecycle dish .*Standard tray/ })
        .check();
    await page.getByRole("button", { name: "Add selection group" }).click();
    await expect(
        page.getByRole("heading", { name: /draft Version 1/i }),
    ).toBeVisible();

    // Review and explicitly confirm publication.
    await page.getByRole("link", { name: "Review and publish" }).click();
    await expect(
        page.getByRole("heading", { name: "Review publication" }),
    ).toBeVisible();
    await expect(page.getByText("Included main dish")).toBeVisible();
    await page.getByLabel(/I reviewed this configuration/).check();
    await page.getByRole("button", { name: "Publish Version 1" }).click();
    await expect(
        page.getByRole("heading", { name: /published Version 1/i }),
    ).toBeVisible();

    // Historical publication remains available while revision creates a new draft.
    await page.getByRole("button", { name: "Restore as a new draft" }).click();
    await expect(
        page.getByRole("heading", { name: /draft Version 2/i }),
    ).toBeVisible();

    // Disable first, then archive the package; the confirmation is a real browser dialog.
    await page.goto("/admin/packages/products");
    await page.getByRole("link", { name: product, exact: true }).click();
    await expect(
        page.getByRole("link", { name: /View published version 1/i }),
    ).toBeVisible();
    await expect(
        page.getByRole("link", { name: /Configure draft 2/i }),
    ).toBeVisible();
    const disableResponse = page.waitForResponse(
        (response) =>
            response.url().includes("/availability") &&
            response.request().method() === "POST",
    );
    await page
        .getByRole("button", { name: "Temporarily disable product" })
        .click();
    const disableResult = await disableResponse;
    expect(disableResult.status()).toBeLessThan(400);
    await page.goto(page.url());
    await expect(
        page.getByRole("button", { name: "Enable product" }),
    ).toBeVisible();
    page.once("dialog", (dialog) => dialog.accept());
    await page.getByRole("button", { name: "Archive product" }).click();
    await page.goto("/admin/packages/products");
    await expect(page.getByText(/Archived$/, { exact: false })).toBeVisible();

    const result = execFileSync(
        "php",
        ["tests/Browser/assert-publication-lifecycle.php"],
        {
            env: { ...process.env, BROWSER_RUN: run },
            encoding: "utf8",
        },
    );
    expect(result).toContain('"published_version":1');
    expect(result).toContain('"archived":true');
    expect(result).toContain('"cloned_version":2');
    expect(result).toContain('"cloned_status":"abandoned"');
});
