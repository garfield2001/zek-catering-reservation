"use client";

import { CircleDollarSign } from "lucide-react";
import { money } from "@/lib/catering";
import type { InquiryTotals } from "../domain/types";

export function SummaryPanel({ totals }: { totals: InquiryTotals }) {
  return (
    <div className="rounded-md border border-neutral-200 bg-neutral-50 p-4">
      <div className="grid gap-2 text-sm text-neutral-600 sm:grid-cols-4">
        <Summary label="Base" value={money(totals.base)} />
        <Summary label="Premiums" value={money(totals.premiums)} />
        <Summary label="Add-ons" value={money(totals.addons)} />
        <Summary label="Estimate" value={money(totals.total)} strong />
      </div>
    </div>
  );
}

function Summary({ label, value, strong }: { label: string; value: string; strong?: boolean }) {
  return (
    <div>
      <div className="flex items-center gap-2 text-xs uppercase tracking-[0.12em] text-neutral-500">
        {strong && <CircleDollarSign className="h-3.5 w-3.5" />}
        {label}
      </div>
      <p className={`mt-1 ${strong ? "text-xl font-semibold text-neutral-950" : "font-medium text-neutral-800"}`}>{value}</p>
    </div>
  );
}
