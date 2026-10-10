import AdminLayout from "../../../../layouts/admin-layout";
import { FoodItemForm } from "../../../../features/catalog/components/food-item-form";
import type {
    FoodCategory,
    FoodItem,
    Collection,
} from "../../../../features/catalog/types";
export default function DishEdit({
    dish,
    categories,
    families,
}: {
    dish: FoodItem | null;
    categories: FoodCategory[];
    families: Collection[];
}) {
    return (
        <AdminLayout
            title={dish ? "Edit food item" : "Create food item"}
            description="Assign one food category and any number of eligible collections."
        >
            <FoodItemForm
                dish={dish}
                categories={categories}
                families={families}
            />
        </AdminLayout>
    );
}
