import AdminLayout from "../../../../layouts/admin-layout";
import { FamilyDefaultForm } from "../../../../features/catalog/components/family-default-form";
import type { Family } from "../../../../features/catalog/types";
export default function FamilyIndex({ families }: { families: Family[] }) {
    return (
        <AdminLayout
            title="Family ordering defaults"
            description="Admin-controlled rules inherited by future package and listing drafts. Changes are audited."
        >
            <div className="space-y-5">
                {families.map((family) => (
                    <FamilyDefaultForm key={family.id} family={family} />
                ))}
            </div>
        </AdminLayout>
    );
}
