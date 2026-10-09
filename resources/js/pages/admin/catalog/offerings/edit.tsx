import AdminLayout from "../../../../layouts/admin-layout";
import { OfferingForm } from "../../../../features/catalog/components/offering-form";
import type {
    Dish,
    Family,
    Offering,
} from "../../../../features/catalog/types";
export default function OfferingEdit({
    dish,
    offering,
    families,
}: {
    dish: Dish;
    offering: Offering | null;
    families: Family[];
}) {
    return (
        <AdminLayout
            title={offering ? "Edit offering" : "Create offering"}
            description={"Dish: " + dish.name}
        >
            <OfferingForm dish={dish} offering={offering} families={families} />
        </AdminLayout>
    );
}
