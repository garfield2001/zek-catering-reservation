"use client";

import { Check, Plus } from "lucide-react";

export function Field({
  label,
  name,
  type = "text",
  required,
  placeholder,
  min,
}: {
  label: string;
  name: string;
  type?: string;
  required?: boolean;
  placeholder?: string;
  min?: string;
}) {
  return (
    <label className="text-sm font-medium text-neutral-800">
      {label}
      <input
        name={name}
        type={type}
        required={required}
        placeholder={placeholder}
        min={min}
        className="mt-2 h-11 w-full rounded-md border border-neutral-300 px-3 text-sm outline-none focus:border-neutral-950"
      />
    </label>
  );
}

export function PickerHeader({ title, detail }: { title: string; detail: string }) {
  return (
    <div className="flex items-center justify-between gap-4">
      <h2 className="text-lg font-semibold text-neutral-950">{title}</h2>
      <span className="rounded-full border border-neutral-200 px-3 py-1 text-xs text-neutral-500">{detail}</span>
    </div>
  );
}

export function ChoiceCard({
  selected,
  title,
  meta,
  description,
  initials,
  onClick,
}: {
  selected: boolean;
  title: string;
  meta: string;
  description: string | null;
  initials?: string;
  onClick: () => void;
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      className={`min-h-28 rounded-md border p-4 text-left transition ${
        selected ? "border-neutral-950 bg-neutral-950 text-white" : "border-neutral-200 bg-white text-neutral-950 hover:border-neutral-400"
      }`}
    >
      <div className="flex items-start gap-3">
        <span className={`flex size-11 shrink-0 items-center justify-center rounded-md text-xs font-semibold ${selected ? "bg-white/15 text-white" : "bg-[color:var(--brand-cream)] text-[color:var(--brand-maroon)]"}`}>
          {initials ?? getInitials(title)}
        </span>
        <div className="min-w-0 flex-1">
          <p className="text-sm font-semibold">{title}</p>
          <p className={`mt-1 text-xs ${selected ? "text-neutral-300" : "text-neutral-500"}`}>{meta}</p>
        </div>
        {selected ? <Check className="h-4 w-4 shrink-0" /> : <Plus className="h-4 w-4 shrink-0 text-neutral-400" />}
      </div>
      {description && <p className={`mt-3 text-sm leading-5 ${selected ? "text-neutral-200" : "text-neutral-600"}`}>{description}</p>}
    </button>
  );
}

function getInitials(value: string) {
  return value
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase())
    .join("");
}
