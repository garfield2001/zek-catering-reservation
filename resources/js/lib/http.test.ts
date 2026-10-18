import { afterEach, describe, expect, it, vi } from "vitest";
import { csrfFetch, sessionFailureMessage } from "./http";

afterEach(() => {
    document.cookie = "XSRF-TOKEN=; Max-Age=0; path=/";
    vi.unstubAllGlobals();
});

describe("same-origin session requests", () => {
    it("reads the current cookie on every submission and removes conflicting stale headers", async () => {
        const fetch = vi.fn().mockResolvedValue(new Response("{}"));
        vi.stubGlobal("fetch", fetch);
        for (const token of ["before=", "after="]) {
            document.cookie = `XSRF-TOKEN=${encodeURIComponent(token)}; path=/`;
            await csrfFetch("/admin/example", {
                method: "POST",
                headers: { "X-CSRF-TOKEN": "stale", "X-XSRF-TOKEN": "stale" },
            });
            const options = fetch.mock.lastCall![1];
            expect(options.credentials).toBe("same-origin");
            expect(options.headers.has("X-CSRF-TOKEN")).toBe(false);
            expect(options.headers.get("X-XSRF-TOKEN")).toBe(token);
        }
        expect(fetch).toHaveBeenCalledTimes(2);
    });

    it("preserves multipart bodies without setting a boundary or retrying a rejected write", async () => {
        const fetch = vi
            .fn()
            .mockResolvedValue(new Response("{}", { status: 419 }));
        vi.stubGlobal("fetch", fetch);
        const body = new FormData();
        body.append("image", new File(["image"], "sample.png"));
        const response = await csrfFetch("/admin/example", {
            method: "POST",
            body,
        });
        expect(response.status).toBe(419);
        expect(fetch).toHaveBeenCalledTimes(1);
        expect(fetch.mock.lastCall![1].body).toBe(body);
        expect(fetch.mock.lastCall![1].headers.has("Content-Type")).toBe(false);
        expect(fetch.mock.lastCall![1].headers.has("X-XSRF-TOKEN")).toBe(false);
    });

    it("does not send session headers to another origin", () => {
        const fetch = vi.fn();
        vi.stubGlobal("fetch", fetch);
        expect(() =>
            csrfFetch("https://unrelated.example/write", { method: "POST" }),
        ).toThrow();
        expect(fetch).not.toHaveBeenCalled();
    });

    it("distinguishes expired sessions from validation and permission errors", () => {
        for (const status of [401, 419])
            expect(
                sessionFailureMessage(new Response("", { status })),
            ).toContain("Sign in in another tab");
        for (const status of [200, 403, 422])
            expect(
                sessionFailureMessage(new Response("", { status })),
            ).toBeUndefined();
        const redirected = new Response("");
        Object.defineProperties(redirected, {
            redirected: { value: true },
            url: { value: "http://localhost/login" },
        });
        expect(sessionFailureMessage(redirected)).toContain(
            "unsaved input is still here",
        );
    });
});
