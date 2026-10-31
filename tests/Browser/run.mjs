import { spawn } from "node:child_process";
import { request } from "node:http";
import { resolve } from "node:path";

const port = process.env.PLAYWRIGHT_PORT || "8011";
const baseUrl = `http://127.0.0.1:${port}`;
const uploadTemporaryDirectory = resolve("storage/framework/cache");
const php = process.env.PHP_BINARY || "php";
const testingEnvironment = {
    ...process.env,
    APP_ENV: "testing",
    // Do not inherit an operator's local Laravel caches. The browser server
    // must always route from the current source while it serves the isolated
    // test database.
    APP_CONFIG_CACHE: resolve("storage/framework/browser-tests-config.php"),
    APP_ROUTES_CACHE: resolve("storage/framework/browser-tests-routes.php"),
    DB_CONNECTION: "pgsql",
    DB_DATABASE: "zek_catering_test",
    DB_HOST: "127.0.0.1",
    DB_PORT: "5432",
};
const server = spawn(
    php,
    [
        "-d",
        `upload_tmp_dir=${uploadTemporaryDirectory}`,
        "-S",
        `127.0.0.1:${port}`,
        "-t",
        "public",
        "tests/Browser/router.php",
    ],
    { env: testingEnvironment, stdio: "ignore", windowsHide: true },
);

const waitForTestingServer = () =>
    new Promise((resolveReady, rejectReady) => {
        const deadline = Date.now() + 60_000;
        const poll = () => {
            const probe = request(`${baseUrl}/login`, (response) => {
                response.resume();
                if (
                    response.statusCode === 200 &&
                    response.headers["x-catering-test-database"] ===
                        "zek_catering_test:5432"
                ) {
                    resolveReady();
                    return;
                }
                retry();
            });
            probe.on("error", retry);
            probe.end();
        };
        const retry = () => {
            if (Date.now() >= deadline) {
                rejectReady(
                    new Error(
                        "The guarded Playwright test server did not start.",
                    ),
                );
                return;
            }
            setTimeout(poll, 250);
        };
        poll();
    });

let serverStopped = false;

const stopProcessTree = (childProcess) => {
    if (childProcess.pid === undefined) return Promise.resolve();

    // The wrapper owns only this PHP server. It does not spawn Playwright
    // workers, so terminating the direct child is both sufficient and more
    // reliable than a second Windows process-tree command during teardown.
    if (childProcess.exitCode === null) childProcess.kill("SIGTERM");

    return Promise.resolve();
};

const stopServer = () => {
    if (serverStopped) return Promise.resolve();

    serverStopped = true;

    // The child PID comes from the test server created above.
    return stopProcessTree(server);
};

const runPlaywright = () =>
    new Promise((resolveExit) => {
        const playwright = spawn(
            process.execPath,
            [
                resolve("node_modules/@playwright/test/cli.js"),
                "test",
                ...process.argv.slice(2),
            ],
            {
                env: { ...testingEnvironment, PLAYWRIGHT_EXTERNAL_SERVER: "1" },
                stdio: ["ignore", "pipe", "pipe"],
                windowsHide: true,
            },
        );
        let settled = false;

        const settle = (code) => {
            if (settled) return;

            settled = true;
            resolveExit(code ?? 1);
        };
        const forward = (stream, write) => {
            stream.on("data", (chunk) => {
                const text = chunk.toString();

                write(text);
            });
        };

        forward(playwright.stdout, (text) => process.stdout.write(text));
        forward(playwright.stderr, (text) => process.stderr.write(text));
        playwright.on("error", () => settle(1));
        playwright.on("exit", (code) => settle(code));
    });

try {
    await waitForTestingServer();
    process.exitCode = await runPlaywright();
} finally {
    await stopServer();
}
