import { defineConfig } from "vitest/config";
import laravel from "laravel-vite-plugin";
import react from "@vitejs/plugin-react";
import tailwindcss from "@tailwindcss/vite";

export default defineConfig({
    plugins: [
        laravel({
            input: ["resources/css/app.css", "resources/js/app.tsx"],
            refresh: true,
        }),
        react(),
        tailwindcss(),
    ],
    test: {
        environment: "jsdom",
        setupFiles: ["resources/js/test-setup.ts"],
        include: ["resources/js/**/*.test.{ts,tsx}"],
    },
});
