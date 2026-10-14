import type { ButtonHTMLAttributes } from "react";
export function Button({
    className = "",
    variant = "primary",
    ...props
}: ButtonHTMLAttributes<HTMLButtonElement> & {
    variant?: "primary" | "secondary" | "danger" | "ghost";
}) {
    const variants = {
        primary: "bg-emerald-800 text-white hover:bg-emerald-900",
        secondary:
            "border border-slate-300 bg-white text-slate-700 hover:bg-slate-50",
        danger: "bg-rose-700 text-white hover:bg-rose-800",
        ghost: "text-slate-600 hover:bg-slate-100",
    };
    return (
        <button
            className={
                "inline-flex min-h-10 items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold transition-colors disabled:cursor-not-allowed disabled:opacity-50 motion-reduce:transition-none " +
                variants[variant] +
                " " +
                className
            }
            {...props}
        />
    );
}
