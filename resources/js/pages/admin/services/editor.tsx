import { Link } from "@inertiajs/react";
import { ServiceEditorForm } from "../../../features/catalog/components/service-editor-form";
import type { Collection } from "../../../features/catalog/types";
import AdminLayout from "../../../layouts/admin-layout";

export default function ServiceEditor({
    family,
}: {
    family: Collection | null;
}) {
    const editing = family !== null;
    return (
        <AdminLayout
            title={editing ? `Edit ${family.name}` : "Create Add-on Service"}
            description={
                editing
                    ? "Update service presentation without rewriting existing commercial history."
                    : "Set how this optional service will be sold before configuring packages or individual items."
            }
            section="services"
        >
            <nav
                aria-label="Breadcrumb"
                className="mb-5 text-sm text-slate-600"
            >
                <Link
                    className="text-emerald-800 underline"
                    href="/admin/add-on-services"
                >
                    Add-on Services
                </Link>
                {editing && <span> / {family.name}</span>}
                <span> / {editing ? "Edit" : "Create"}</span>
            </nav>
            <ServiceEditorForm family={family} />
        </AdminLayout>
    );
}
