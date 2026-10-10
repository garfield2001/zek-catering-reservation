import AdminLayout from "../../../../layouts/admin-layout";
import { DishForm } from "../../../../features/catalog/components/dish-form";
import type {
    Category,
    Dish,
    Family,
} from "../../../../features/catalog/types";
export default function DishEdit({
    dish,
    categories,
    families,
}: {
    dish: Dish | null;
    categories: Category[];
    families: Family[];
}) {
    return (
        <AdminLayout
            title={dish ? "Edit dish" : "Create dish"}
            description="Assign one food category and any number of eligible collections."
        >
            <DishForm dish={dish} categories={categories} families={families} />
        </AdminLayout>
    );
}
