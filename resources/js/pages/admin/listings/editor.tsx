import AdminLayout from "../../../layouts/admin-layout";
import { Link } from "@inertiajs/react";
import { ListingForm } from "../../../features/listings/components/listing-form";
import type { ListingEditorProps } from "../../../features/listings/types";
export default function ListingEditor(props: ListingEditorProps) {
    return (
        <AdminLayout
            title={props.listing ? "Edit Item" : "Create Item"}
            description={props.family.name}
            section="services"
        >
            <nav
                aria-label="Breadcrumb"
                className="mb-5 text-sm text-slate-600"
            >
                <Link
                    className="text-emerald-800 underline"
                    href={`/admin/add-on-services/${props.family.id}`}
                >
                    Back to {props.family.name}
                </Link>
                <span> / {props.listing ? "Edit Item" : "Create Item"}</span>
            </nav>
            <ListingForm {...props} />
        </AdminLayout>
    );
}
