import { act, cleanup, renderHook } from "@testing-library/react";
import { afterEach, beforeEach, expect, it, vi } from "vitest";
import { router } from "@inertiajs/react";
import { useLiveFilters } from "./use-live-filters";

vi.mock("@inertiajs/react", () => ({ router: { get: vi.fn() } }));
beforeEach(() => vi.useFakeTimers());
afterEach(() => {
    cleanup();
    vi.useRealTimers();
    vi.clearAllMocks();
});

it("debounces combined filters and resets pagination without submitting a form", () => {
    const { result } = renderHook(() =>
        useLiveFilters("/foods", { q: "", category: "all" }),
    );
    expect(router.get).not.toHaveBeenCalled();
    act(() => result.current.setFilters({ q: "ri", category: "3" }));
    act(() => vi.advanceTimersByTime(200));
    act(() => result.current.setFilters({ q: "rice", category: "3" }));
    act(() => vi.advanceTimersByTime(299));
    expect(router.get).not.toHaveBeenCalled();
    act(() => vi.advanceTimersByTime(1));
    expect(router.get).toHaveBeenCalledWith(
        "/foods",
        { q: "rice", category: "3" },
        expect.objectContaining({ preserveState: true, replace: true }),
    );
});

it("cancels stale requests and retries a cancelled query rather than treating it as loaded", () => {
    const cancel = vi.fn();
    vi.mocked(router.get).mockImplementation((_url, _data, options) => {
        options?.onCancelToken?.({ cancel });
    });
    const { result } = renderHook(() => useLiveFilters("/foods", { q: "" }));
    act(() => result.current.setFilters({ q: "rice" }));
    act(() => vi.advanceTimersByTime(300));
    act(() => result.current.setFilters({ q: "rice cake" }));
    expect(cancel).toHaveBeenCalledOnce();
    act(() => result.current.setFilters({ q: "rice" }));
    act(() => vi.advanceTimersByTime(300));
    expect(router.get).toHaveBeenCalledTimes(2);
});
