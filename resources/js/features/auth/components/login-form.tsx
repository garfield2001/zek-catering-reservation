import { useForm } from "@inertiajs/react";
import { Button } from "../../../components/ui/button";
import { Field } from "../../../components/ui/field";
export function LoginForm() {
    const form = useForm({ email: "", password: "" });
    return (
        <form
            className="space-y-5"
            onSubmit={(event) => {
                event.preventDefault();
                form.post("/login", { onFinish: () => form.reset("password") });
            }}
        >
            <Field id="email" label="Email address" error={form.errors.email}>
                <input
                    id="email"
                    type="email"
                    autoComplete="username"
                    required
                    autoFocus
                    value={form.data.email}
                    onChange={(e) => form.setData("email", e.target.value)}
                    aria-invalid={!!form.errors.email}
                    aria-describedby={
                        form.errors.email ? "email-error" : undefined
                    }
                />
            </Field>
            <Field id="password" label="Password" error={form.errors.password}>
                <input
                    id="password"
                    type="password"
                    autoComplete="current-password"
                    required
                    value={form.data.password}
                    onChange={(e) => form.setData("password", e.target.value)}
                />
            </Field>
            <Button className="w-full" disabled={form.processing}>
                Sign in
            </Button>
        </form>
    );
}
