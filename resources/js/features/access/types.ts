export type StaffAccount = {
    id: number;
    name: string;
    email: string;
    active: boolean;
    access_version: number;
    role: "admin" | "staff";
};
export type StaffRow = Omit<StaffAccount, "role"> & {
    roles: string[];
    canUpdate: boolean;
    canManagePermissions: boolean;
};
export type Permission = { code: string; description: string };
export type Effect = "allow" | "deny";
