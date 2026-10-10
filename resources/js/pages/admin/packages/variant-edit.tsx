import AdminLayout from "../../../layouts/admin-layout";
import { VariantForm } from "../../../features/packages/components/variant-form";
import type { OrderDefault } from "../../../features/catalog/types";
import type {
    DraftVersion,
    PackageProduct,
    PackageVariant,
} from "../../../features/packages/types";

export default function VariantEdit({
    product,
    variant,
    version,
    defaults,
}: {
    product: PackageProduct;
    variant: PackageVariant | null;
    version: DraftVersion | null;
    defaults: OrderDefault;
}) {
    return (
        <AdminLayout
            title={
                variant
                    ? `Edit ${variant.name}`
                    : `Add variant to ${product.name}`
            }
            description="Base price and quantity rules are authoritative only for this administrative draft preview."
        >
            <VariantForm
                product={product}
                variant={variant}
                version={version}
                defaults={defaults}
            />
        </AdminLayout>
    );
}
