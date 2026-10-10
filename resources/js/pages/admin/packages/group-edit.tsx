import AdminLayout from "../../../layouts/admin-layout";
import { SelectionGroupForm } from "../../../features/packages/components/selection-group-form";
import type {
    AvailableServingOption,
    VariantVersion,
    SelectionGroup,
    PackageProduct,
    PackageVariant,
} from "../../../features/packages/types";

export default function GroupEdit({
    product,
    variant,
    version,
    group,
    availableOptions,
    categories,
    canPrice,
}: {
    product: PackageProduct;
    variant: PackageVariant;
    version: VariantVersion;
    group: SelectionGroup | null;
    availableOptions: AvailableServingOption[];
    categories: { id: number; name: string }[];
    canPrice: boolean;
}) {
    return (
        <AdminLayout
            title={group ? `Edit ${group.name}` : "Add selection group"}
            description="Configure included items or customer choices, allowed serving options, counts and optional food category or item rules."
        >
            <SelectionGroupForm
                product={product}
                variant={variant}
                version={version}
                group={group}
                availableOptions={availableOptions}
                categories={categories}
                canPrice={canPrice}
            />
        </AdminLayout>
    );
}
