import { createInertiaApp } from "@inertiajs/react";
import { createRoot } from "react-dom/client";
import type { ComponentType } from "react";
import { Notifications } from "./components/ui/notifications";
const pages = import.meta.glob<{ default: ComponentType }>("./pages/**/*.tsx");
createInertiaApp({
    title: (title) => title + " | Zek Catering",
    resolve: async (name) =>
        (await pages["./pages/" + name + ".tsx"]()).default,
    setup({ el, App, props }) {
        createRoot(el).render(
            <>
                <App {...props} />
                <Notifications />
            </>,
        );
    },
    progress: { color: "#047857" },
});
