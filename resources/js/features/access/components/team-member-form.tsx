import { useForm, usePage } from "@inertiajs/react";
import { Button } from "../../../components/ui/button";
import { Field } from "../../../components/ui/field";
import type { SharedProps } from "../../../types";
import type { UserAccount } from "../types";
export function TeamMemberForm({ staff }: { staff: UserAccount | null }) {
    const { auth } = usePage<SharedProps>().props;
    const form = useForm({
        name: staff?.name ?? "",
        email: staff?.email ?? "",
        password: "",
        password_confirmation: "",
        active: staff?.active ?? true,
        role: staff?.role ?? "staff",
        access_version: staff?.access_version ?? 1,
        confirm_access_change: false,
    });
    return (
        <form
            className="max-w-xl space-y-5 rounded-xl border border-stone-200 bg-white p-6"
            onSubmit={(event) => {
                event.preventDefault();
                const options = {
                    onFinish: () =>
                        form.reset("password", "password_confirmation"),
                };
                if (staff) {
                    const losingAdmin =
                        staff.role === "admin" &&
                        (form.data.role !== "admin" || !form.data.active);
                    if (
                        losingAdmin &&
                        !window.confirm(
                            "This removes Administrator access and revokes existing sessions. Continue only if another active Administrator can manage accounts and permissions.",
                        )
                    )
                        return;
                    form.transform((data) => ({
                        ...data,
                        confirm_access_change: losingAdmin,
                    }));
                    form.put("/admin/staff/" + staff.id, options);
                } else form.post("/admin/staff", options);
            }}
        >
            <Field id="name" label="Full name" error={form.errors.name}>
                <input
                    id="name"
                    value={form.data.name}
                    required
                    onChange={(e) => form.setData("name", e.target.value)}
                />
            </Field>
            <Field id="email" label="Email" error={form.errors.email}>
                <input
                    id="email"
                    type="email"
                    value={form.data.email}
                    required
                    onChange={(e) => form.setData("email", e.target.value)}
                />
            </Field>
            <Field id="role" label="Role" error={form.errors.role}>
                <select
                    id="role"
                    value={form.data.role}
                    onChange={(e) =>
                        form.setData(
                            "role",
                            e.target.value as "admin" | "staff",
                        )
                    }
                >
                    <option
                        value="staff"
                        disabled={staff?.protectedAdministrator}
                    >
                        Staff
                    </option>
                    {auth?.isAdmin && (
                        <option value="admin">
                            Administrator — full access
                        </option>
                    )}
                </select>
            </Field>
            <Field
                id="password"
                label={
                    staff
                        ? "New password (leave blank to keep)"
                        : "Initial password"
                }
                error={form.errors.password}
            >
                <input
                    id="password"
                    type="password"
                    autoComplete="new-password"
                    required={!staff}
                    value={form.data.password}
                    onChange={(e) => form.setData("password", e.target.value)}
                />
            </Field>
            <p className="text-sm text-stone-500">
                At least 12 characters, including uppercase, lowercase and a
                number. Share securely with the account holder.
            </p>
            <Field id="password_confirmation" label="Confirm password">
                <input
                    id="password_confirmation"
                    type="password"
                    autoComplete="new-password"
                    value={form.data.password_confirmation}
                    onChange={(e) =>
                        form.setData("password_confirmation", e.target.value)
                    }
                />
            </Field>
            <label className="flex items-center gap-2 text-sm">
                <input
                    type="checkbox"
                    checked={form.data.active}
                    disabled={staff?.protectedAdministrator}
                    onChange={(e) => form.setData("active", e.target.checked)}
                />
                Account is active
            </label>
            {form.errors.active && (
                <p role="alert" className="text-sm text-red-700">
                    {form.errors.active}
                </p>
            )}
            {staff?.protectedAdministrator && (
                <p className="text-sm text-amber-800">
                    This is the last active Administrator. Create or activate
                    another Administrator before deactivating or changing this
                    role.
                </p>
            )}
            {form.errors.confirm_access_change && (
                <p role="alert" className="text-sm text-red-700">
                    {form.errors.confirm_access_change}
                </p>
            )}
            <Button disabled={form.processing}>
                {staff ? "Save account" : "Create account"}
            </Button>
        </form>
    );
}
