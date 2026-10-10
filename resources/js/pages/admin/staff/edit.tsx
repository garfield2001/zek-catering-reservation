import AdminLayout from "../../../layouts/admin-layout";
import { TeamMemberForm } from "../../../features/access/components/team-member-form";
import type { UserAccount } from "../../../features/access/types";
export default function EditTeamMember({
    staff,
}: {
    staff: UserAccount | null;
}) {
    return (
        <AdminLayout
            title={staff ? "Edit account" : "Create account"}
            description="Only active accounts can sign in. Saving changes revokes existing sessions."
        >
            <TeamMemberForm staff={staff} />
        </AdminLayout>
    );
}
