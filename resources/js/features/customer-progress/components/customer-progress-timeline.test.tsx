import { render, screen } from "@testing-library/react";
import { expect, it } from "vitest";
import { CustomerProgressTimeline } from "./customer-progress-timeline";

it("does not present verified payment readiness as reservation confirmation", () => {
    render(
        <CustomerProgressTimeline
            requestStatus="under_review"
            reservationStatus={null}
            cancellationStatuses={[]}
            quotation={{
                status: "issued",
                accepted: true,
                paymentStatuses: ["verified"],
                paymentSummary: {
                    reservation_payment_satisfied: true,
                    verified_minor: 150000,
                },
            }}
        />,
    );

    expect(screen.getByText("Reservation payment verified")).toBeVisible();
    expect(screen.getByText("Awaiting confirmation")).toBeVisible();
    expect(screen.queryByText("Reservation confirmed")).not.toBeInTheDocument();
    expect(
        screen.getByText(/only an Administrator can confirm availability/i),
    ).toBeVisible();
});

it("shows actual completed and cancellation states without inventing a refund outcome", () => {
    const { rerender } = render(
        <CustomerProgressTimeline
            requestStatus="under_review"
            reservationStatus="completed"
            cancellationStatuses={[]}
            quotation={null}
        />,
    );

    expect(screen.getByText("Event completed")).toBeVisible();

    rerender(
        <CustomerProgressTimeline
            requestStatus="under_review"
            reservationStatus="confirmed"
            cancellationStatuses={["refund_under_review"]}
            quotation={null}
        />,
    );

    expect(screen.getAllByText(/Refund under review/i).length).toBeGreaterThan(
        0,
    );
    expect(
        screen.getByText(
            /does not by itself cancel a reservation or approve a refund/i,
        ),
    ).toBeVisible();
});
