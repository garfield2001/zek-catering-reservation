"use client";

import { FormEvent, useState } from "react";
import Link from "next/link";
import { AlertCircle, CheckCircle2, Loader2, LockKeyhole } from "lucide-react";
import { createClient } from "@/lib/client";

export default function ResetPasswordPage() {
  const [password, setPassword] = useState("");
  const [confirmPassword, setConfirmPassword] = useState("");
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState(false);

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError(null);
    setSuccess(false);

    if (password.length < 8) {
      setError("Password must be at least 8 characters.");
      return;
    }

    if (password !== confirmPassword) {
      setError("Passwords do not match.");
      return;
    }

    setLoading(true);
    const supabase = createClient();
    const { error: updateError } = await supabase.auth.updateUser({ password });
    setLoading(false);

    if (updateError) {
      setError(updateError.message);
      return;
    }

    setSuccess(true);
    setPassword("");
    setConfirmPassword("");
  }

  return (
    <main className="flex min-h-screen items-center justify-center bg-neutral-950 px-4 py-10 text-neutral-950">
      <form onSubmit={handleSubmit} className="w-full max-w-md rounded-md bg-white p-6 shadow-2xl sm:p-8">
        <div className="flex size-11 items-center justify-center rounded-md bg-neutral-950 text-white">
          <LockKeyhole className="h-5 w-5" />
        </div>
        <p className="mt-6 text-xs font-medium uppercase tracking-[0.2em] text-neutral-500">Admin access</p>
        <h1 className="mt-3 text-3xl font-semibold tracking-tight">Set new password</h1>
        <p className="mt-2 text-sm leading-6 text-neutral-600">
          Use a strong password for your owner or staff account.
        </p>

        <label className="mt-6 block text-sm font-medium">
          New password
          <input
            value={password}
            onChange={(event) => setPassword(event.target.value)}
            type="password"
            autoComplete="new-password"
            required
            disabled={loading}
            className="mt-2 h-11 w-full rounded-md border border-neutral-300 px-3 text-sm outline-none focus:border-neutral-950 disabled:bg-neutral-100"
          />
        </label>

        <label className="mt-4 block text-sm font-medium">
          Confirm password
          <input
            value={confirmPassword}
            onChange={(event) => setConfirmPassword(event.target.value)}
            type="password"
            autoComplete="new-password"
            required
            disabled={loading}
            className="mt-2 h-11 w-full rounded-md border border-neutral-300 px-3 text-sm outline-none focus:border-neutral-950 disabled:bg-neutral-100"
          />
        </label>

        {error && (
          <p className="mt-4 flex items-start gap-2 rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700" role="alert">
            <AlertCircle className="mt-0.5 h-4 w-4 shrink-0" />
            {error}
          </p>
        )}

        {success && (
          <p className="mt-4 flex items-start gap-2 rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700" role="status">
            <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0" />
            Password updated. You can sign in with your new password.
          </p>
        )}

        <button
          disabled={loading}
          className="mt-6 inline-flex h-11 w-full items-center justify-center gap-2 rounded-md bg-neutral-950 px-5 text-sm font-medium text-white hover:bg-neutral-800 disabled:cursor-not-allowed disabled:opacity-70"
        >
          {loading && <Loader2 className="h-4 w-4 animate-spin" />}
          {loading ? "Updating password..." : "Update password"}
        </button>

        <Link href="/admin/login" className="mt-4 inline-flex text-sm font-medium text-neutral-700 hover:text-neutral-950">
          Back to sign in
        </Link>
      </form>
    </main>
  );
}
