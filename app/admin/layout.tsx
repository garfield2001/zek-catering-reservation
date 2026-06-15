import { AdminShell } from "./admin-shell";
import { getCurrentProfile } from "@/lib/auth";

export default async function AdminLayout({ children }: { children: React.ReactNode }) {
  const profile = await getCurrentProfile();

  if (!profile) return <>{children}</>;

  return <AdminShell profile={profile}>{children}</AdminShell>;
}
