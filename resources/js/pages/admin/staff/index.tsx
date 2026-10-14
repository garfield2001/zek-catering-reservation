import { Link } from "@inertiajs/react";
import { TeamTable } from "../../../features/access/components/team-table";
import { Button } from "../../../components/ui/button";
import AdminLayout from "../../../layouts/admin-layout";
import { PaginationControls } from "../../../components/ui/pagination";
import type { Pagination } from "../../../types";
import type { TeamMemberRow } from "../../../features/access/types";
export default function TeamMemberIndex({
    users,
    canCreate,
    filters,
}: {
    users: Pagination<TeamMemberRow>;
    canCreate: boolean;
    filters: { q?: string; status?: string; sort?: string; direction?: string };
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
            <form
                method="get"
                action="/admin/staff"
                className="mb-5 flex flex-wrap items-end gap-3 rounded-xl border bg-white p-4"
            >
                <label className="min-w-48 flex-1 text-xs font-medium text-slate-600">
                    Search team
                    <input
                        name="q"
                        defaultValue={filters.q}
                        placeholder="Name or email"
                        className="mt-1"
                    />
                </label>
                <label className="text-xs font-medium text-slate-600">
                    Account status
                    <select
                        name="status"
                        defaultValue={filters.status ?? ""}
                        className="mt-1"
                    >
                        <option value="">All statuses</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </label>
                <label className="text-xs font-medium text-slate-600">
                    Sort by
                    <select
                        name="sort"
                        defaultValue={filters.sort ?? "name"}
                        className="mt-1"
                    >
                        <option value="name">Name</option>
                        <option value="email">Email</option>
                        <option value="created_at">Created</option>
                    </select>
                </label>
                <label className="text-xs font-medium text-slate-600">
                    Order
                    <select
                        name="direction"
                        defaultValue={filters.direction ?? "asc"}
                        className="mt-1"
                    >
                        <option value="asc">Ascending</option>
                        <option value="desc">Descending</option>
                    </select>
                </label>
                <Button variant="secondary">Apply filters</Button>
            </form>
            <TeamTable users={users.data} />
            <PaginationControls {...users} />
        </AdminLayout>
    );
}
