import AdminLayout from "../../../../layouts/admin-layout";
import { CategoryForm } from "../../../../features/catalog/components/category-form";
import type { Category } from "../../../../features/catalog/types";
export default function CategoryEdit({
    category,
}: {
    category: Category | null;
}) {
    return (
        <AdminLayout
            title={category ? "Edit category" : "Create category"}
            description="Codes are stable identifiers. Deactivation retains existing dishes."
        >
            <CategoryForm category={category} />
        </AdminLayout>
    );
}
