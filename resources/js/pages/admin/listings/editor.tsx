import AdminLayout from "../../../layouts/admin-layout";
import { ListingForm } from "../../../features/listings/components/listing-form";
import type { ListingEditorProps } from "../../../features/listings/types";
export default function ListingEditor(props: ListingEditorProps) {
    return (
        <AdminLayout
            title={props.listing ? "Edit Item" : "Create Item"}
            description={props.family.name}
            section="services"
        >
            <ListingForm {...props} />
        </AdminLayout>
    );
}
