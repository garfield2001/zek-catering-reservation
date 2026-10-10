import AdminLayout from "../../../layouts/admin-layout";
import { GroupForm } from "../../../features/packages/components/group-form";
import type {
    AvailableOption,
    DraftVersion,
    PackageGroup,
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
    version: DraftVersion;
    group: PackageGroup | null;
    availableOptions: AvailableOption[];
    categories: { id: number; name: string }[];
    canPrice: boolean;
}) {
    return (
        <AdminLayout
            title={group ? `Edit ${group.name}` : "Add selection slot"}
            description="Choose allowed options, defaults, counts and optional category or dish restrictions."
        >
            <GroupForm
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
