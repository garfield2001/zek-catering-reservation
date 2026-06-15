"use client";

import { AlertCircle, Loader2 } from "lucide-react";

export function CatalogLoadingState() {
  return (
    <div className="grid gap-4 rounded-md border border-neutral-200 bg-white p-5" aria-live="polite">
      <div className="flex items-center gap-3 rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
        <Loader2 className="h-4 w-4 animate-spin" />
        Loading current packages, meals, drinks, and add-ons...
      </div>
      {Array.from({ length: 6 }).map((_, index) => (
        <div key={index} className="h-16 rounded-md bg-neutral-100" />
      ))}
    </div>
  );
}

export function CatalogErrorState({ error }: { error: string }) {
  return (
    <div className="rounded-md border border-red-200 bg-white p-6" role="alert">
      <div className="flex items-start gap-3">
        <AlertCircle className="mt-0.5 h-5 w-5 shrink-0 text-red-600" />
        <div>
          <p className="font-semibold text-neutral-950">Menu catalog did not load</p>
          <p className="mt-2 text-sm leading-6 text-neutral-600">{error}</p>
          <button type="button" onClick={() => window.location.reload()} className="mt-4 h-10 rounded-md bg-neutral-950 px-4 text-sm font-medium text-white">
            Refresh page
          </button>
        </div>
      </div>
    </div>
  );
}
