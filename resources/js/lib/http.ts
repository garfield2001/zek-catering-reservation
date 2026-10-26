/** Laravel's current XSRF cookie, matching Inertia/Axios's same-origin convention.
 * Read at submission time: login/logout rotate it without replacing the HTML.
 * Never retry mutations or force a multipart Content-Type (the browser owns it).
 */
export function csrfFetch(
    url: string,
    options: RequestInit,
): Promise<Response> {
    if (new URL(url, window.location.href).origin !== window.location.origin)
        throw new Error("Requests must stay on this application.");
    const headers = new Headers(options.headers);
    headers.delete("X-CSRF-TOKEN");
    headers.delete("X-XSRF-TOKEN");
    const cookie = document.cookie
        .split(";")
        .map((part) => part.trim())
        .find((part) => part.startsWith("XSRF-TOKEN="));
    if (cookie)
        headers.set(
            "X-XSRF-TOKEN",
            decodeURIComponent(cookie.slice("XSRF-TOKEN=".length)),
        );
    headers.set("Accept", "application/json");
    return fetch(url, { ...options, credentials: "same-origin", headers });
}

export function sessionFailureMessage(response: Response): string | undefined {
    if (
        response.status === 401 ||
        response.status === 419 ||
        (response.redirected && new URL(response.url).pathname === "/login")
    )
        return "Your session expired or changed. Your unsaved input is still here. Sign in in another tab, then return and submit again.";
}
