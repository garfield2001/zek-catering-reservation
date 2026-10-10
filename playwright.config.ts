import { defineConfig } from "@playwright/test";
const port = process.env.PLAYWRIGHT_PORT || "8011";
export default defineConfig({
    testDir: "./tests/Browser",
    workers: 1,
    use: {
        baseURL: `http://127.0.0.1:${port}`,
        channel: process.env.PLAYWRIGHT_CHANNEL || undefined,
        trace: "retain-on-failure",
    },
    webServer: {
        command: `php artisan serve --env=testing --host=127.0.0.1 --port=${port}`,
        url: `http://127.0.0.1:${port}/login`,
        reuseExistingServer: process.env.PLAYWRIGHT_REUSE_SERVER === "1",
        timeout: 30000,
    },
});
