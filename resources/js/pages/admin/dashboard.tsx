import { OperationsDashboard } from "../../features/dashboard/components/operations-dashboard";
import AdminLayout from "../../layouts/admin-layout";

type DashboardCard = {
    label: string;
    count: number;
    href: string;
    description: string;
    tone: "neutral" | "attention" | "success";
};

type UpcomingReservation = {
    id: number;
    reference: string;
    customerName: string;
    eventDate: string;
    eventStartTime: string | null;
    guestCount: number | null;
    status: string;
};

type QuickAction = {
    label: string;
    description: string;
    href: string;
};

export default function Dashboard({
    setup,
    operations,
    quickActions,
}: {
    setup: {
        categories: number | null;
        items: number | null;
        products: number | null;
    };
    operations: {
        cards: DashboardCard[];
        upcomingReservations: UpcomingReservation[];
    };
    quickActions: QuickAction[];
}) {
    const setupEntries = Object.entries(setup).filter(
        ([, value]) => value !== null,
    );

    return (
        <AdminLayout
            title="Dashboard"
            description="An operational overview of the records you can access. Open a queue to review the underlying record; dashboard counts do not change workflow state."
        >
            <OperationsDashboard
                cards={operations.cards}
                upcomingReservations={operations.upcomingReservations}
                quickActions={quickActions}
            />
            {setupEntries.length > 0 && (
                <section className="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 className="font-semibold text-slate-950">
                        Catalog setup
                    </h2>
                    <p className="mt-1 text-sm text-slate-600">
                        Current catalog counts. Published packages still require
                        their own configuration and publication review.
                    </p>
                    <dl className="mt-4 grid gap-3 sm:grid-cols-3">
                        {setupEntries.map(([label, value]) => (
                            <div
                                key={label}
                                className="rounded-xl bg-slate-50 p-4"
                            >
                                <dt className="text-sm capitalize text-slate-600">
                                    {label}
                                </dt>
                                <dd className="mt-1 text-2xl font-semibold text-slate-950">
                                    {value}
                                </dd>
                            </div>
                        ))}
                    </dl>
                </section>
            )}
        </AdminLayout>
    );
}
