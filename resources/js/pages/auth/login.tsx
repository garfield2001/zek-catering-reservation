import { Head, usePage } from "@inertiajs/react";
import { LoginForm } from "../../features/auth/components/login-form";
import type { SharedProps } from "../../types";
export default function Login() {
    const { flash } = usePage<SharedProps>().props;
    return (
        <main className="flex min-h-screen items-center justify-center px-6 py-12">
            <Head title="Sign in" />
            <div className="w-full max-w-md rounded-2xl border border-stone-200 bg-white p-9 shadow-sm">
                <p className="mb-8 text-sm font-semibold uppercase tracking-widest text-emerald-800">
                    Zek Catering
                </p>
                <h1 className="text-3xl font-semibold">Welcome back</h1>
                <p className="mb-8 mt-2 text-stone-600">
                    Sign in to your administration workspace.
                </p>
                {flash.status && (
                    <p role="status" className="mb-4 text-sm text-emerald-800">
                        {flash.status}
                    </p>
                )}
                <LoginForm />
                <p className="mt-6 text-sm text-stone-500">
                    Access is provided by your administrator.
                </p>
            </div>
        </main>
    );
}
