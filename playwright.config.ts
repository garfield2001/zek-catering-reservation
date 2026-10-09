import { defineConfig } from "@playwright/test";
export default defineConfig({
    testDir: "./tests/Browser",
    workers: 1,
    use: {
        baseURL: "http://127.0.0.1:8011",
        channel: process.env.PLAYWRIGHT_CHANNEL || undefined,
        trace: "retain-on-failure",
    },
    webServer: {
        command: "php artisan serve --env=testing --host=127.0.0.1 --port=8011",
        url: "http://127.0.0.1:8011/login",
        reuseExistingServer: process.env.PLAYWRIGHT_REUSE_SERVER === "1",
        timeout: 30000,
    },
});
