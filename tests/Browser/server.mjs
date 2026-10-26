import { spawn } from "node:child_process";
import { resolve } from "node:path";

const port = process.env.PLAYWRIGHT_PORT || "8011";
const uploadTemporaryDirectory = resolve("storage/framework/cache");
const php = process.env.PHP_BINARY || "php";
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
    { env: process.env, stdio: "inherit", windowsHide: true },
);

let stopping = false;
const stop = () => {
    if (stopping) return;

    stopping = true;
    server.kill();
};

process.on("SIGINT", stop);
process.on("SIGTERM", stop);
server.on("error", (error) => {
    console.error(error);
    process.exitCode = 1;
});
server.on("exit", (code) => {
    process.exit(code ?? 0);
});
