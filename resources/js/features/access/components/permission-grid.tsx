import type { Effect, Permission } from "../types";

const groups: Record<string, string> = {
    dashboard: "Dashboard",
    catalog: "Food Catalog",
    family: "Collections & Add-ons",
    package: "Catering Packages & Add-ons",
    staff: "Team Members",
    access: "Team & Access",
    audit: "Activity History",
};

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
    const sections = Object.entries(groups)
        .map(([prefix, title]) => ({
            title,
            permissions: permissions.filter((permission) =>
                permission.code.startsWith(prefix + "."),
            ),
        }))
        .filter((section) => section.permissions.length > 0);
    const otherPermissions = permissions.filter(
        (permission) =>
            !Object.keys(groups).some((prefix) =>
                permission.code.startsWith(prefix + "."),
            ),
    );
    if (otherPermissions.length > 0) {
        sections.push({
            title: "Other predefined permissions",
            permissions: otherPermissions,
        });
    }

    return (
        <div className="space-y-6">
            {sections.map((section) => (
                <section key={section.title}>
                    <h2 className="font-semibold text-slate-900">
                        {section.title}
                    </h2>
                    <div className="divide-y divide-stone-200">
                        {section.permissions.map((permission) => {
                            const inherited = roleGrants.includes(
                                permission.code,
                            );
                            const override = overrides[permission.code];
                            const effective = override
                                ? override === "allow"
                                : inherited;
                            return (
                                <div
                                    key={permission.code}
                                    className="grid gap-3 py-4 sm:grid-cols-[1fr_160px]"
                                >
                                    <label htmlFor={permission.code}>
                                        <span className="font-medium">
                                            {permission.description}
                                        </span>
                                        <span className="mt-1 block text-xs text-stone-500">
                                            Role:{" "}
                                            {inherited
                                                ? "Allowed"
                                                : "Not granted"}{" "}
                                            · Account override:{" "}
                                            {override ?? "None"} · Effective:{" "}
                                            {effective ? "Allowed" : "Denied"}
                                        </span>
                                    </label>
                                    <select
                                        id={permission.code}
                                        value={overrides[permission.code] ?? ""}
                                        disabled={
                                            !grantable.includes(permission.code)
                                        }
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
                            );
                        })}
                    </div>
                </section>
            ))}
        </div>
    );
}
