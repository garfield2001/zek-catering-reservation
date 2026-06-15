"use client";

import { FormEvent, Suspense, useState } from "react";
import { AlertCircle, CheckCircle2, Loader2, ShieldCheck } from "lucide-react";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { createClient } from "@/lib/client";

export default function AdminLoginPage() {
  return (
    <Suspense fallback={null}>
      <LoginForm />
    </Suspense>
  );
}

function LoginForm() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState(false);
  const [loading, setLoading] = useState(false);

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setLoading(true);
    setSuccess(false);
    setError(null);

    const normalizedEmail = email.trim().toLowerCase();
    const supabase = createClient();
    const { error: signInError } = await supabase.auth.signInWithPassword({ email: normalizedEmail, password });

    if (signInError) {
      setLoading(false);
      setError("Invalid email or password. Check your staff account and try again.");
      return;
    }

    setSuccess(true);
    router.replace(searchParams.get("next") ?? "/admin/dashboard");
    router.refresh();
  }

  return (
    <main className="flex min-h-screen items-center justify-center bg-neutral-950 px-4 py-10 text-neutral-950">
      <div className="grid w-full max-w-4xl overflow-hidden rounded-md bg-white shadow-2xl lg:grid-cols-[0.9fr_1.1fr]">
        <aside className="hidden bg-[linear-gradient(180deg,#3f241b_0%,#171717_100%)] p-8 text-white lg:block">
          <div className="flex size-12 items-center justify-center rounded-md bg-white/10">
            <ShieldCheck className="h-6 w-6" />
          </div>
          <h1 className="mt-8 text-3xl font-semibold tracking-tight">Zek Catering Staff Portal</h1>
          <p className="mt-4 text-sm leading-6 text-white/75">
            Review inquiries, prepare quotations, verify payments, and keep reservations moving from one admin area.
          </p>
          <div className="mt-8 grid gap-3 text-sm text-white/80">
            <p className="rounded-md border border-white/15 bg-white/10 p-3">1. Check new inquiries</p>
            <p className="rounded-md border border-white/15 bg-white/10 p-3">2. Build or update quotation</p>
            <p className="rounded-md border border-white/15 bg-white/10 p-3">3. Confirm reservation fee payment</p>
          </div>
        </aside>
        <form onSubmit={handleSubmit} className="p-6 sm:p-8">
          <p className="text-xs font-medium uppercase tracking-[0.2em] text-neutral-500">Admin access</p>
          <h2 className="mt-3 text-3xl font-semibold tracking-tight">Sign in</h2>
          <p className="mt-2 text-sm leading-6 text-neutral-600">
            Use the email and password created for your owner or staff account.
          </p>
          <label className="mt-6 block text-sm font-medium">
            Email address
            <input
              value={email}
              onChange={(event) => setEmail(event.target.value)}
              type="email"
              autoComplete="email"
              required
              disabled={loading}
              className="mt-2 h-11 w-full rounded-md border border-neutral-300 px-3 text-sm outline-none focus:border-neutral-950 disabled:bg-neutral-100"
            />
          </label>
          <label className="mt-4 block text-sm font-medium">
            Password
            <input
              value={password}
              onChange={(event) => setPassword(event.target.value)}
              type="password"
              autoComplete="current-password"
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
              Login accepted. Opening the admin dashboard...
            </p>
          )}
          <button
            disabled={loading}
            className="mt-6 inline-flex h-11 w-full items-center justify-center gap-2 rounded-md bg-neutral-950 px-5 text-sm font-medium text-white hover:bg-neutral-800 disabled:cursor-not-allowed disabled:opacity-70"
          >
            {loading && <Loader2 className="h-4 w-4 animate-spin" />}
            {loading ? "Signing in and loading admin..." : "Sign in"}
          </button>
          <Link href="/admin/forgot-password" className="mt-4 inline-flex text-sm font-medium text-neutral-700 hover:text-neutral-950">
            Forgot password?
          </Link>
        </form>
      </div>
    </main>
  );
}
