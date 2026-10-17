import { expect, it } from "vitest";
import {
    demoDeadlines,
    demoEvents,
    demoMonths,
    demoSummary,
} from "./demo-data";

it("keeps illustrative financial and reservation totals consistent in each reporting period", () => {
    for (const period of [3, 6]) {
        const months = demoMonths.slice(-period);
        const totals = demoSummary(months);
        expect(totals.booking).toBe(totals.collected + totals.outstanding);
        expect(totals.unverified).toBeLessThanOrEqual(totals.outstanding);
        expect(totals.confirmed).toBe(
            months.reduce((sum, month) => sum + month.confirmed, 0),
        );
        expect(
            demoEvents.filter((event) => event.status === "Confirmed").length,
        ).toBeLessThanOrEqual(totals.confirmed);
        expect(
            demoDeadlines.reduce((sum, deadline) => sum + deadline.amount, 0),
        ).toBeLessThanOrEqual(totals.outstanding);
    }
});

it("demonstrates overlapping schedules without labelling them conflicts", () => {
    const [first, second] = demoEvents;
    expect(first.date).toBe(second.date);
    expect(second.start < first.end && first.start < second.end).toBe(true);
    expect(first.status).toBe("Confirmed");
    expect(second.status).toBe("Confirmed");
});
