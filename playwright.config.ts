import { defineConfig } from "@playwright/test";
const port = process.env.PLAYWRIGHT_PORT || "8011";
export default defineConfig({
    globalSetup: "./tests/Browser/global-setup.ts",
    testDir: "./tests/Browser",
    workers: 1,
    use: {
        baseURL: `http://127.0.0.1:${port}`,
        channel: process.env.PLAYWRIGHT_CHANNEL || undefined,
        trace: "retain-on-failure",
    },
    webServer:
        process.env.PLAYWRIGHT_EXTERNAL_SERVER === "1"
            ? undefined
            : {
                  command: `php -S 127.0.0.1:${port} -t public tests/Browser/router.php`,
                  env: {
                      APP_ENV: "testing",
                      DB_CONNECTION: "pgsql",
                      DB_DATABASE: "zek_catering_test",
                      DB_PORT: "5432",
                  },
                  url: `http://127.0.0.1:${port}/login`,
                  reuseExistingServer: false,
                  timeout: 60000,
                  stderr: "ignore",
              },
});
