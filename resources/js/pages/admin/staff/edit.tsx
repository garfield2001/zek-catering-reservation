import AdminLayout from "../../../layouts/admin-layout";
import { StaffForm } from "../../../features/access/components/staff-form";
import type { StaffAccount } from "../../../features/access/types";
export default function EditStaff({ staff }: { staff: StaffAccount | null }) {
    return (
        <AdminLayout
            title={staff ? "Edit account" : "Create account"}
            description="Only active accounts can sign in. Saving changes revokes existing sessions."
        >
            <StaffForm staff={staff} />
        </AdminLayout>
    );
}
