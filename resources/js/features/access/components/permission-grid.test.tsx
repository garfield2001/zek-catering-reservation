import { render, screen, fireEvent, cleanup } from "@testing-library/react";
import { afterEach, expect, test, vi } from "vitest";
import { PermissionGrid } from "./permission-grid";
afterEach(cleanup);
test("shows inherited access and emits explicit deny; ungrantable capabilities stay disabled", () => {
    const change = vi.fn();
    render(
        <PermissionGrid
            permissions={[
                { code: "dashboard.view", description: "Dashboard" },
                { code: "access.manage", description: "Permissions" },
            ]}
            overrides={{}}
            roleGrants={["dashboard.view"]}
            grantable={["dashboard.view"]}
            onChange={change}
        />,
    );
    expect(screen.getByText(/Role: Allowed/)).toHaveTextContent(
        "Effective: Allowed",
    );
    fireEvent.change(screen.getByLabelText("Dashboard", { exact: false }), {
        target: { value: "deny" },
    });
    expect(change).toHaveBeenCalledWith("dashboard.view", "deny");
    expect(
        screen.getByLabelText("Permissions", { exact: false }),
    ).toBeDisabled();
});

test("shows an explicit deny separately from the inherited role grant", () => {
    render(
        <PermissionGrid
            permissions={[
                { code: "dashboard.view", description: "View dashboard" },
            ]}
            overrides={{ "dashboard.view": "deny" }}
            roleGrants={["dashboard.view"]}
            grantable={["dashboard.view"]}
            onChange={vi.fn()}
        />,
    );
    expect(screen.getByText(/Role: Allowed/)).toHaveTextContent(
        "Account override: deny",
    );
    expect(screen.getByText(/Role: Allowed/)).toHaveTextContent(
        "Effective: Denied",
    );
});
