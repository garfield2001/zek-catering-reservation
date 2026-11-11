import { render, screen } from "@testing-library/react";
import { expect, it } from "vitest";
import { CaseWorkflow } from "./case-workflow";

it("presents completed, current, blocked, and next case steps with contextual navigation", () => {
    render(
        <CaseWorkflow
            steps={[
                {
                    label: "Inquiry",
                    detail: "RQ-100",
                    state: "complete",
                    href: "/admin/reservation-requests/100",
                },
                {
                    label: "Quotation",
                    detail: "Accepted version 2",
                    state: "current",
                },
                {
                    label: "Payment",
                    detail: "Payment submitted; awaiting verification.",
                    state: "blocked",
                },
                {
                    label: "Reservation",
                    detail: "Capacity is not committed until confirmation.",
                    state: "upcoming",
                },
            ]}
        />,
    );

    expect(
        screen.getByRole("navigation", { name: "Reservation case progress" }),
    ).toBeVisible();
    expect(screen.getByText("Completed")).toBeVisible();
    expect(screen.getByText("Current step")).toBeVisible();
    expect(screen.getByText("Needs attention")).toBeVisible();
    expect(screen.getByText("Next")).toBeVisible();
    expect(screen.getByRole("link", { name: "Inquiry" })).toHaveAttribute(
        "href",
        "/admin/reservation-requests/100",
    );
    expect(
        screen.getByText("Capacity is not committed until confirmation."),
    ).toBeVisible();
});
