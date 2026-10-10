import AdminLayout from "../../../layouts/admin-layout";
import { ProductForm } from "../../../features/packages/components/product-form";
import type { Family } from "../../../features/catalog/types";
import type { PackageProduct } from "../../../features/packages/types";

export default function ProductEdit({
    product,
    families,
}: {
    product: PackageProduct | null;
    families: Family[];
}) {
    return (
        <AdminLayout
            title={product ? `Edit ${product.name}` : "Create package product"}
            description="Products group named package variants within one family."
        >
            <ProductForm product={product} families={families} />
        </AdminLayout>
    );
}
