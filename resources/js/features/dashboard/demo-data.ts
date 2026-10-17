/** Illustrative data only. Never a source of commercial or operational decisions. */
export const demoAsOf = "15 October 2026";
export const demoMonths = [
    {
        month: "May",
        confirmed: 8,
        pending: 3,
        cancelled: 1,
        inquiries: 6,
        quotations: 3,
        proofs: 1,
        booking: 640000,
        collected: 480000,
        unverified: 20000,
    },
    {
        month: "Jun",
        confirmed: 10,
        pending: 4,
        cancelled: 1,
        inquiries: 7,
        quotations: 4,
        proofs: 1,
        booking: 820000,
        collected: 600000,
        unverified: 25000,
    },
    {
        month: "Jul",
        confirmed: 9,
        pending: 2,
        cancelled: 1,
        inquiries: 5,
        quotations: 2,
        proofs: 1,
        booking: 760000,
        collected: 520000,
        unverified: 30000,
    },
    {
        month: "Aug",
        confirmed: 12,
        pending: 4,
        cancelled: 2,
        inquiries: 8,
        quotations: 4,
        proofs: 2,
        booking: 960000,
        collected: 680000,
        unverified: 40000,
    },
    {
        month: "Sep",
        confirmed: 14,
        pending: 5,
        cancelled: 1,
        inquiries: 9,
        quotations: 5,
        proofs: 2,
        booking: 1120000,
        collected: 800000,
        unverified: 45000,
    },
    {
        month: "Oct",
        confirmed: 16,
        pending: 6,
        cancelled: 1,
        inquiries: 12,
        quotations: 6,
        proofs: 3,
        booking: 1360000,
        collected: 920000,
        unverified: 60000,
    },
];

export const demoEvents = [
    {
        id: "demo-1",
        date: "2026-10-17",
        type: "Wedding",
        customer: "Sample Couple A",
        venue: "Demo Garden Hall",
        start: "11:00",
        end: "15:00",
        guests: 180,
        status: "Confirmed",
        review: true,
    },
    {
        id: "demo-2",
        date: "2026-10-17",
        type: "Company lunch",
        customer: "Sample Company B",
        venue: "Demo Business Center",
        start: "12:00",
        end: "14:00",
        guests: 150,
        status: "Confirmed",
        review: true,
    },
    {
        id: "demo-3",
        date: "2026-10-18",
        type: "Birthday",
        customer: "Sample Family C",
        venue: "Demo Events Pavilion",
        start: "17:00",
        end: "21:00",
        guests: 160,
        status: "Confirmed",
        review: false,
    },
    {
        id: "demo-4",
        date: "2026-10-20",
        type: "Anniversary",
        customer: "Sample Couple D",
        venue: "Demo Function Room",
        start: "18:00",
        end: "21:00",
        guests: 150,
        status: "Tentative",
        review: false,
    },
];

export const demoActivity = [
    ["10:45", "Menu change requested", "Sample Family C · awaiting review"],
    ["10:30", "Reservation confirmed", "Sample Company B · demonstration"],
    ["10:15", "Payment verified", "Sample Couple A · demonstration"],
    ["09:50", "Payment submitted", "Sample Family C · not yet verified"],
    ["09:20", "Quotation issued", "Sample Company E · demonstration"],
    ["09:00", "Inquiry received", "Sample Family F · demonstration"],
];

export const demoDeadlines = [
    { customer: "Sample Couple A", due: "16 October", amount: 45000 },
    { customer: "Sample Company B", due: "16 October", amount: 30000 },
];

export function demoSummary(months: typeof demoMonths) {
    const sum = (key: keyof (typeof demoMonths)[number]) =>
        months.reduce((total, month) => total + Number(month[key]), 0);
    return {
        confirmed: sum("confirmed"),
        pending: sum("pending"),
        cancelled: sum("cancelled"),
        inquiries: sum("inquiries"),
        quotations: sum("quotations"),
        proofs: sum("proofs"),
        booking: sum("booking"),
        collected: sum("collected"),
        outstanding: sum("booking") - sum("collected"),
        unverified: sum("unverified"),
    };
}

export const formatDemoMoney = (amount: number) =>
    new Intl.NumberFormat("en-PH", {
        style: "currency",
        currency: "PHP",
        maximumFractionDigits: 0,
    }).format(amount);
