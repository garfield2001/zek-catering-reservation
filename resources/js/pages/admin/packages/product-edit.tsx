import AdminLayout from "../../../layouts/admin-layout";
import { ProductForm } from "../../../features/packages/components/product-form";
import type { Collection } from "../../../features/catalog/types";
import type { PackageProduct } from "../../../features/packages/types";

export default function ProductEdit({
    product,
    families,
    selectedFamilyId,
}: {
    product: PackageProduct | null;
    families: Collection[];
    selectedFamilyId?: number | null;
}) {
    return (
        <AdminLayout
            title={product ? `Edit ${product.name}` : "Create package product"}
            description="Package products group named variants within one collection."
        >
            <ProductForm
                product={product}
                families={families}
                selectedFamilyId={selectedFamilyId}
            />
        </AdminLayout>
    );
}
