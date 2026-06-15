import { type EmailOtpType } from "@supabase/supabase-js";
import { NextResponse } from "next/server";
import { createClient } from "@/lib/server";

export async function GET(request: Request) {
  const url = new URL(request.url);
  const tokenHash = url.searchParams.get("token_hash");
  const type = url.searchParams.get("type") as EmailOtpType | null;
  const code = url.searchParams.get("code");
  const requestedNext = url.searchParams.get("next") ?? "/admin/reset-password";
  const next = requestedNext.startsWith("/") && !requestedNext.startsWith("//") ? requestedNext : "/admin/reset-password";
  const redirectTo = new URL(next, url.origin);

  const supabase = await createClient();

  if (tokenHash && type) {
    const { error } = await supabase.auth.verifyOtp({ token_hash: tokenHash, type });
    if (!error) return NextResponse.redirect(redirectTo);
  }

  if (code) {
    const { error } = await supabase.auth.exchangeCodeForSession(code);
    if (!error) return NextResponse.redirect(redirectTo);
  }

  return NextResponse.redirect(new URL("/admin/login?error=auth-confirmation-failed", url.origin));
}
