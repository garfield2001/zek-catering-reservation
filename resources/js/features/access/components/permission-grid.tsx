import type { Effect, Permission } from "../types";
export function PermissionGrid({
    permissions,
    overrides,
    roleGrants,
    grantable,
    onChange,
}: {
    permissions: Permission[];
    overrides: Record<string, Effect>;
    roleGrants: string[];
    grantable: string[];
    onChange: (code: string, effect: Effect | "") => void;
}) {
    return (
        <div className="divide-y divide-stone-200">
            {permissions.map((permission) => (
                <div
                    key={permission.code}
                    className="grid gap-3 py-4 sm:grid-cols-[1fr_160px]"
                >
                    <label htmlFor={permission.code}>
                        <span className="font-medium">
                            {permission.description}
                        </span>
                        <span className="mt-1 block text-xs text-stone-500">
                            Role default:{" "}
                            {roleGrants.includes(permission.code)
                                ? "Allowed"
                                : "Denied"}
                        </span>
                    </label>
                    <select
                        id={permission.code}
                        value={overrides[permission.code] ?? ""}
                        disabled={!grantable.includes(permission.code)}
                        onChange={(e) =>
                            onChange(
                                permission.code,
                                e.target.value as Effect | "",
                            )
                        }
                    >
                        <option value="">Inherit role</option>
                        <option value="allow">Allow</option>
                        <option value="deny">Deny</option>
                    </select>
                </div>
            ))}
        </div>
    );
}
