import { Link } from "@inertiajs/react";
import AdminLayout from "../../../layouts/admin-layout";
import { PaginationControls } from "../../../components/ui/pagination";
import type { Pagination } from "../../../types";
import type { TeamMemberRow } from "../../../features/access/types";
export default function TeamMemberIndex({
    users,
    canCreate,
}: {
    users: Pagination<TeamMemberRow>;
    canCreate: boolean;
}) {
    return (
        <AdminLayout
            title="Team Members"
            description="Administrator and Staff accounts, roles and assigned permissions."
        >
            {canCreate && (
                <Link
                    href="/admin/staff/create"
                    className="mb-5 inline-block rounded-lg bg-emerald-800 px-4 py-2 text-sm font-semibold text-white"
                >
                    Create account
                </Link>
            )}
            <div className="overflow-x-auto rounded-xl border border-stone-200 bg-white">
                <table className="w-full text-left text-sm">
                    <thead className="border-b bg-stone-100">
                        <tr>
                            {["Account", "Role", "Status", "Actions"].map(
                                (label) => (
                                    <th key={label} className="px-5 py-3">
                                        {label}
                                    </th>
                                ),
                            )}
                        </tr>
                    </thead>
                    <tbody>
                        {users.data.map((user) => (
                            <tr
                                key={user.id}
                                className="border-b border-stone-100"
                            >
                                <td className="px-5 py-4">
                                    <div className="font-medium">
                                        {user.name}
                                    </div>
                                    <div className="text-stone-500">
                                        {user.email}
                                    </div>
                                </td>
                                <td className="px-5 py-4 capitalize">
                                    {user.roles
                                        .map((role) =>
                                            role === "admin"
                                                ? "Administrator"
                                                : "Staff",
                                        )
                                        .join(", ")}
                                </td>
                                <td className="px-5 py-4">
                                    {user.active ? "Active" : "Inactive"}
                                    {user.protectedAdministrator && (
                                        <p className="text-xs text-amber-800">
                                            Last active Administrator — role and
                                            active status protected
                                        </p>
                                    )}
                                </td>
                                <td className="space-x-3 px-5 py-4">
                                    {user.canUpdate && (
                                        <Link
                                            className="underline"
                                            href={
                                                "/admin/staff/" +
                                                user.id +
                                                "/edit"
                                            }
                                        >
                                            Edit
                                        </Link>
                                    )}
                                    {user.canManagePermissions && (
                                        <Link
                                            className="underline"
                                            href={
                                                "/admin/staff/" +
                                                user.id +
                                                "/permissions"
                                            }
                                        >
                                            Permissions
                                        </Link>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <PaginationControls {...users} />
        </AdminLayout>
    );
}
