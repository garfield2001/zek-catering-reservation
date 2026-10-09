import type { ButtonHTMLAttributes } from "react";
export function Button({
    className = "",
    ...props
}: ButtonHTMLAttributes<HTMLButtonElement>) {
    return (
        <button
            className={
                "inline-flex items-center justify-center rounded-lg bg-emerald-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-900 disabled:opacity-50 " +
                className
            }
            {...props}
        />
    );
}
