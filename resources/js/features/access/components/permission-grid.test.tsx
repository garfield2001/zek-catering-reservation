import { render, screen, fireEvent } from "@testing-library/react";
import { expect, test, vi } from "vitest";
import { PermissionGrid } from "./permission-grid";
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
    expect(screen.getByText("Role default: Allowed")).toBeInTheDocument();
    fireEvent.change(screen.getByLabelText("Dashboard", { exact: false }), {
        target: { value: "deny" },
    });
    expect(change).toHaveBeenCalledWith("dashboard.view", "deny");
    expect(
        screen.getByLabelText("Permissions", { exact: false }),
    ).toBeDisabled();
});
