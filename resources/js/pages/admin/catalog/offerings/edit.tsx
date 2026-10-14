import { Link } from "@inertiajs/react";
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
            title={offering ? "Edit preparation" : "Add preparation"}
            description={"Food item: " + dish.name}
        >
            <Link
                href="/admin/food-catalog"
                className="mb-5 inline-block text-sm text-emerald-800 underline"
            >
                Back to Food Catalog
            </Link>
            <OfferingForm dish={dish} offering={offering} families={families} />
        </AdminLayout>
    );
}
