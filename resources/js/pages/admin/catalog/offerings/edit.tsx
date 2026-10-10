import AdminLayout from "../../../../layouts/admin-layout";
import { OfferingForm } from "../../../../features/catalog/components/offering-form";
import type {
    FoodItem,
    Collection,
    Offering,
} from "../../../../features/catalog/types";
export default function OfferingEdit({
    dish,
    offering,
    families,
}: {
    dish: FoodItem;
    offering: Offering | null;
    families: Collection[];
}) {
    return (
        <AdminLayout
            title={offering ? "Edit offering" : "Create offering"}
            description={"Food item: " + dish.name}
        >
            <OfferingForm dish={dish} offering={offering} families={families} />
        </AdminLayout>
    );
}
