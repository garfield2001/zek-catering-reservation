"use client";

import Link from "next/link";
import { ClipboardCheck, Copy, Search } from "lucide-react";
import type { InquiryResult } from "../domain/types";

export function InquirySuccessPanel({
  result,
  copied,
  onCopy,
  onNewInquiry,
}: {
  result: InquiryResult;
  copied: boolean;
  onCopy: () => void;
  onNewInquiry: () => void;
}) {
  return (
    <div className="rounded-md border border-emerald-200 bg-white p-6 shadow-sm" role="status" aria-live="polite">
      <div className="flex flex-col gap-5 sm:flex-row sm:items-start">
        <div className="flex size-12 shrink-0 items-center justify-center rounded-md bg-emerald-600 text-white">
          <ClipboardCheck className="h-6 w-6" />
        </div>
        <div className="min-w-0 flex-1">
          <p className="text-xs font-medium uppercase tracking-[0.18em] text-emerald-700">Inquiry submitted successfully</p>
          <h2 className="mt-3 text-3xl font-semibold text-neutral-950">We received your catering inquiry.</h2>
          <p className="mt-3 text-sm leading-6 text-neutral-600">
            Save this reference code. Staff will review your menu, pax, event date, and notes before sending the final quotation.
            This is not yet a confirmed reservation.
          </p>
          <div className="mt-5 rounded-md border border-emerald-200 bg-emerald-50 p-4">
            <p className="text-xs font-medium uppercase tracking-[0.16em] text-emerald-700">Reference code</p>
            <div className="mt-2 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
              <p className="break-all text-3xl font-semibold tracking-tight text-emerald-950">{result.refCode}</p>
              <button type="button" onClick={onCopy} className="inline-flex h-10 items-center justify-center gap-2 rounded-md border border-emerald-300 bg-white px-4 text-sm font-medium text-emerald-900 hover:bg-emerald-100">
                <Copy className="h-4 w-4" />
                {copied ? "Copied" : "Copy code"}
              </button>
            </div>
          </div>
          <div className="mt-5 grid gap-3 text-sm text-neutral-700 sm:grid-cols-3">
            <Step label="1" title="Inquiry received" detail="Your request is now in the staff queue." />
            <Step label="2" title="Staff review" detail="Zek Catering checks availability and menu details." />
            <Step label="3" title="Reservation fee" detail="Your date is secured after verified payment." />
          </div>
        </div>
      </div>
      <div className="mt-6 flex flex-wrap gap-3 border-t border-neutral-200 pt-5">
        <Link href={`/track/${encodeURIComponent(result.refCode)}`} className="inline-flex h-10 items-center gap-2 rounded-md bg-neutral-950 px-4 text-sm font-medium text-white">
          <Search className="h-4 w-4" />
          Track inquiry
        </Link>
        <button type="button" onClick={onNewInquiry} className="h-10 rounded-md border border-neutral-300 px-4 text-sm font-medium">
          New inquiry
        </button>
      </div>
    </div>
  );
}

function Step({ label, title, detail }: { label: string; title: string; detail: string }) {
  return (
    <div className="rounded-md border border-neutral-200 bg-white p-3">
      <p className="flex size-7 items-center justify-center rounded-md bg-neutral-950 text-xs font-semibold text-white">{label}</p>
      <p className="mt-3 font-semibold text-neutral-950">{title}</p>
      <p className="mt-1 text-xs leading-5 text-neutral-600">{detail}</p>
    </div>
  );
}
