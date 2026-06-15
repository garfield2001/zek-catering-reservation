import { redirect } from "next/navigation";
import { createClient } from "@/lib/server";

export type AppRole = "admin" | "staff";

export async function getCurrentProfile() {
  const supabase = await createClient();
  const {
    data: { user },
  } = await supabase.auth.getUser();

  if (!user) {
    return null;
  }

  const { data } = await supabase
    .from("app_profiles")
    .select("id, auth_user_id, email, full_name, role, is_active")
    .eq("auth_user_id", user.id)
    .eq("is_active", true)
    .single();

  return data as
    | {
        id: string;
        auth_user_id: string;
        email: string;
        full_name: string;
        role: AppRole;
        is_active: boolean;
      }
    | null;
}

export async function requireProfile() {
  const profile = await getCurrentProfile();

  if (!profile) {
    redirect("/admin/login");
  }

  return profile;
}

export async function requireAdmin() {
  const profile = await requireProfile();

  return profile;
}
