export type UserAccount = {
    id: number;
    name: string;
    email: string;
    active: boolean;
    access_version: number;
    role: "admin" | "staff";
    protectedAdministrator: boolean;
};
export type TeamMemberRow = Omit<UserAccount, "role"> & {
    roles: string[];
    canUpdate: boolean;
    canManagePermissions: boolean;
};
export type Permission = { code: string; description: string };
export type Effect = "allow" | "deny";
