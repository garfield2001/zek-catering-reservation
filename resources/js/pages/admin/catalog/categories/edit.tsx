import AdminLayout from "../../../../layouts/admin-layout";
import { CategoryForm } from "../../../../features/catalog/components/category-form";
import type { FoodCategory } from "../../../../features/catalog/types";
export default function CategoryEdit({
    category,
}: {
    category: FoodCategory | null;
}) {
    return (
        <AdminLayout
            title={category ? "Edit category" : "Create category"}
            description="Codes are stable identifiers. Deactivation retains existing food items."
        >
            <CategoryForm category={category} />
        </AdminLayout>
    );
}
