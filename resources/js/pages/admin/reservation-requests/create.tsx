import { Link } from "@inertiajs/react";
import { ArrowLeft } from "lucide-react";
import { InternalRequestForm } from "../../../features/reservation-requests/components/internal-request-form";
import type {
    PublicListing,
    PublicPackage,
} from "../../../features/storefront/types";
import AdminLayout from "../../../layouts/admin-layout";

type Catalog = {
    main_packages: PublicPackage[];
    add_on_services: Array<{
        id: number;
        name: string;
        description: string | null;
        packages: PublicPackage[];
        listings: PublicListing[];
    }>;
};

export default function CreateReservationRequest({
    catalog,
    idempotencyKey,
}: {
    catalog: Catalog;
    idempotencyKey: string;
}) {
    return (
        <AdminLayout
            title="New reservation request"
            description="Create an internal request from current published offerings. This creates an inquiry only; it does not reserve capacity."
            section="requests"
        >
            <Link
                href="/admin/reservation-requests"
                className="inline-flex items-center gap-2 text-sm font-semibold text-emerald-800 hover:underline"
            >
                <ArrowLeft size={16} /> Reservation Requests
            </Link>
            <div className="mt-6">
                <InternalRequestForm
                    catalog={catalog}
                    endpoint="/admin/reservation-requests"
                    idempotencyKey={idempotencyKey}
                />
            </div>
        </AdminLayout>
    );
}
