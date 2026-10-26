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

    if (process.platform !== "win32") {
        if (childProcess.exitCode === null) childProcess.kill("SIGTERM");

        return Promise.resolve();
    }

    // `ChildProcess.kill()` only signals the immediate process on Windows.
    // Playwright can retain Node worker children, which would violate the
    // one-runner test-database guard on the next invocation. Limit taskkill
    // to this wrapper-owned PID and its descendants.
    return new Promise((resolveStop) => {
        const taskkill = spawn(
            "taskkill",
            ["/pid", String(childProcess.pid), "/T", "/F"],
            { stdio: "ignore", windowsHide: true },
        );
        taskkill.once("error", () => {
            if (childProcess.exitCode === null) childProcess.kill("SIGTERM");
            resolveStop();
        });
        taskkill.once("exit", () => resolveStop());
    });
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
    stopServer();
}
