import { Link } from "@inertiajs/react";
import { DataTable, type DataColumn } from "../../../components/ui/data-table";
import type { TeamMemberRow } from "../types";

const columns: DataColumn<TeamMemberRow>[] = [
    {
        accessorKey: "name",
        header: "Account",
        cell: ({ row }) => (
            <>
                <div className="font-medium">{row.original.name}</div>
                <div className="text-slate-500">{row.original.email}</div>
            </>
        ),
    },
    {
        id: "role",
        header: "Role",
        cell: ({ row }) =>
            row.original.roles
                .map((role) => (role === "admin" ? "Administrator" : "Staff"))
                .join(", "),
    },
    {
        accessorKey: "active",
        header: "Status",
        cell: ({ row }) => (
            <>
                <span
                    className={`rounded-full px-2 py-1 text-xs ${row.original.active ? "bg-emerald-50 text-emerald-800" : "bg-slate-100 text-slate-600"}`}
                >
                    {row.original.active ? "Active" : "Inactive"}
                </span>
                {row.original.protectedAdministrator && (
                    <p className="mt-2 max-w-56 text-xs text-amber-800">
                        Last active Administrator — role and active status
                        protected
                    </p>
                )}
            </>
        ),
    },
    {
        id: "actions",
        header: "Actions",
        cell: ({ row }) => (
            <div className="flex gap-3 text-emerald-800">
                {row.original.canUpdate && (
                    <Link
                        href={`/admin/staff/${row.original.id}/edit`}
                        className="underline"
                    >
                        Edit
                    </Link>
                )}
                {row.original.canManagePermissions && (
                    <Link
                        href={`/admin/staff/${row.original.id}/permissions`}
                        className="underline"
                    >
                        Permissions
                    </Link>
                )}
            </div>
        ),
    },
];
export function TeamTable({ users }: { users: TeamMemberRow[] }) {
    return (
        <DataTable
            data={users}
            columns={columns}
            caption="Team member accounts and authorized actions"
        />
    );
}
