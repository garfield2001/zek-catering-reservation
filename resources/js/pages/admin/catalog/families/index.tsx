import AdminLayout from "../../../../layouts/admin-layout";
import { FamilyDefaultForm } from "../../../../features/catalog/components/family-default-form";
import { FamilyPresentationForm } from "../../../../features/catalog/components/family-presentation-form";
import type { Family } from "../../../../features/catalog/types";
export default function FamilyIndex({
    families,
    canManagePresentation,
    canManageDefaults,
}: {
    families: Family[];
    canManagePresentation: boolean;
    canManageDefaults: boolean;
}) {
    return (
        <AdminLayout
            title="Product families"
            description="Display names can change; family codes and primary/add-on identity remain stable. Ordering defaults are managed separately."
        >
            <div className="space-y-5">
                {families.map((family) => (
                    <div key={family.id} className="space-y-3">
                        <FamilyPresentationForm
                            family={family}
                            canManage={canManagePresentation}
                        />
                        {canManageDefaults && (
                            <FamilyDefaultForm family={family} />
                        )}
                    </div>
                ))}
            </div>
        </AdminLayout>
    );
}
