import { useForm } from "@inertiajs/react";
import AdminLayout from "../../../layouts/admin-layout";
import { PermissionGrid } from "../../../features/access/components/permission-grid";
import { Button } from "../../../components/ui/button";
import type { Effect, Permission } from "../../../features/access/types";
export default function Permissions({
    staff,
    permissions,
    overrides,
    roleGrants,
    grantable,
}: {
    staff: { id: number; name: string; access_version: number };
    permissions: Permission[];
    overrides: Record<string, Effect>;
    roleGrants: string[];
    grantable: string[];
}) {
    const form = useForm({
        access_version: staff.access_version,
        overrides: Object.entries(overrides).map(([code, effect]) => ({
            code,
            effect,
        })),
    });
    return (
        <AdminLayout
            title={"Permissions · " + staff.name}
            description="Permissions are predefined by the application. Role grants are inherited; account overrides can allow or deny them. Changes revoke existing sessions."
        >
            <form
                className="max-w-2xl rounded-xl border border-stone-200 bg-white p-6"
                onSubmit={(e) => {
                    e.preventDefault();
                    if (
                        !window.confirm(
                            "Apply these permission changes? Existing sessions for this team member will be revoked.",
                        )
                    )
                        return;
                    form.put("/admin/staff/" + staff.id + "/permissions");
                }}
            >
                <PermissionGrid
                    permissions={permissions}
                    roleGrants={roleGrants}
                    grantable={grantable}
                    overrides={Object.fromEntries(
                        form.data.overrides.map((row) => [
                            row.code,
                            row.effect,
                        ]),
                    )}
                    onChange={(code, effect) =>
                        form.setData("overrides", [
                            ...form.data.overrides.filter(
                                (row) => row.code !== code,
                            ),
                            ...(effect ? [{ code, effect }] : []),
                        ])
                    }
                />
                {Object.values(form.errors).map((error, index) => (
                    <p role="alert" className="text-red-700" key={index}>
                        {error}
                    </p>
                ))}
                <Button className="mt-5" disabled={form.processing}>
                    Save permissions
                </Button>
            </form>
        </AdminLayout>
    );
}
