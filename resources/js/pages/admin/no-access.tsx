import AdminLayout from "../../layouts/admin-layout";

export default function NoAccess() {
    return (
        <AdminLayout
            title="No workspace access"
            description="Your account is active, but no workspace section is assigned. Ask an Administrator to review your permissions."
        >
            <p className="text-sm text-slate-600">
                You can still sign out using the button above.
            </p>
        </AdminLayout>
    );
}
