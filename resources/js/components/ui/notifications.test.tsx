import { cleanup, renderHook } from "@testing-library/react";
import { afterEach, expect, it, vi } from "vitest";
import { toast } from "sonner";
import { useFlashToast } from "./notifications";

vi.mock("sonner", () => ({ toast: { success: vi.fn() }, Toaster: () => null }));
afterEach(() => {
    cleanup();
    vi.clearAllMocks();
});

it("announces each server flash once across rerenders and remounts", () => {
    const flash = { id: "test-single-save", status: "Saved." };
    const { rerender, unmount } = renderHook(() => useFlashToast(flash));
    rerender();
    unmount();
    renderHook(() => useFlashToast(flash));
    expect(toast.success).toHaveBeenCalledTimes(1);
});

it("does not fabricate success and permits separate confirmed saves", () => {
    const { rerender } = renderHook(({ flash }) => useFlashToast(flash), {
        initialProps: { flash: {} as { id?: string; status?: string } },
    });
    expect(toast.success).not.toHaveBeenCalled();
    rerender({ flash: { id: "test-save-a", status: "Saved." } });
    rerender({ flash: { id: "test-save-b", status: "Saved." } });
    expect(toast.success).toHaveBeenCalledTimes(2);
});
