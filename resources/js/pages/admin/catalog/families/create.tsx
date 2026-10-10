import { Link } from "@inertiajs/react";
import { CollectionCreateForm } from "../../../../features/catalog/components/collection-create-form";
import AdminLayout from "../../../../layouts/admin-layout";

export default function CollectionCreate({
    hasPrimary,
}: {
    hasPrimary: boolean;
}) {
    return (
        <AdminLayout
            title="Create collection"
            description="Choose a supported configuration type, then build its catalog and draft products."
        >
            <div className="max-w-2xl rounded-2xl border border-slate-200 bg-white p-6">
                <CollectionCreateForm
                    hasPrimary={hasPrimary}
                    onCancel={() => window.history.back()}
                    onCreated={() => undefined}
                />
            </div>
            <Link
                href="/admin/catalog/families"
                className="mt-5 inline-block text-sm font-semibold text-emerald-800 underline"
            >
                Back to collections
            </Link>
        </AdminLayout>
    );
}
