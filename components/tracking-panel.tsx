"use client";

import { FormEvent, useState } from "react";
import { CheckCircle2, Loader2, Upload } from "lucide-react";
import { money } from "@/lib/catering";

type TrackingPayload = {
  inquiry: {
    refCode: string;
    customerName: string;
    eventType: string;
    eventDate: string;
    eventTime: string | null;
    venue: string;
    status: string;
    pax: number;
    notes: string | null;
    canEdit: boolean;
    editUntil: string | null;
    package: { name: string; price_per_pax: number; meal_slots: number; drink_slots: number } | null;
    selections: Array<{ item_type: string; snapshot_name: string; snapshot_price: number; quantity: number }>;
    requirements: Array<{ requirement_type: string; notes: string | null }>;
  };
  quotation: null | {
    quotation_number: string;
    status: string;
    total_amount: number;
    reservation_fee_amount: number;
    reservation_fee_due_at: string | null;
    final_payment_due_at: string | null;
    valid_until: string | null;
    quotation_items: Array<{ line_type: string; description: string; quantity: number; unit_price: number; amount: number; customer_visible_reason: string | null }>;
  };
  payments: Array<{
    amount: number;
    payment_method: string;
    payment_status: string;
    payment_type: string;
    reference_number: string | null;
    verified_at: string | null;
    rejected_reason: string | null;
    created_at: string;
  }>;
  reservation: null | {
    reservation_code: string;
    status: string;
    event_date: string;
    final_pax: number;
    final_payment_due_at: string | null;
    final_payment_status: string | null;
  };
  paymentInstructions: null | {
    title: string;
    gcashName: string | null;
    gcashNumber: string | null;
    paymayaName: string | null;
    paymayaNumber: string | null;
    bankDetails: string | null;
    notes: string | null;
  };
};

export function TrackingPanel({ initialRef = "" }: { initialRef?: string }) {
  const [refCode, setRefCode] = useState(initialRef);
  const [contactNumber, setContactNumber] = useState("");
  const [data, setData] = useState<TrackingPayload | null>(null);
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  async function lookup(event?: FormEvent<HTMLFormElement>) {
    event?.preventDefault();
    setLoading(true);
    setError(null);
    setMessage(null);
    const response = await fetch("/api/track", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ refCode, contactNumber }),
    });
    const payload = await response.json();
    setLoading(false);
    if (!response.ok) {
      setData(null);
      setError(payload.error ?? "Could not find that inquiry.");
      return;
    }
    setData(payload);
  }

  async function submitForm(event: FormEvent<HTMLFormElement>, endpoint: string, success: string) {
    event.preventDefault();
    setLoading(true);
    setError(null);
    setMessage(null);
    const form = new FormData(event.currentTarget);
    form.set("contact_number", contactNumber);
    const response = await fetch(endpoint, { method: "POST", body: form });
    const payload = await response.json().catch(() => ({}));
    setLoading(false);
    if (!response.ok) {
      setError(payload.error ?? "Action failed.");
      return;
    }
    setMessage(success);
    await lookup();
  }

  return (
    <div className="grid gap-6">
      <form onSubmit={lookup} className="grid gap-4 rounded-md border border-neutral-200 bg-white p-5 sm:grid-cols-[1fr_1fr_auto]">
        <label className="text-sm font-medium text-neutral-800">
          Reference code
          <input value={refCode} onChange={(event) => setRefCode(event.target.value)} className="mt-2 h-11 w-full rounded-md border border-neutral-300 px-3 text-sm" required />
        </label>
        <label className="text-sm font-medium text-neutral-800">
          Contact number
          <input value={contactNumber} onChange={(event) => setContactNumber(event.target.value)} className="mt-2 h-11 w-full rounded-md border border-neutral-300 px-3 text-sm" required />
        </label>
        <button disabled={loading} className="mt-7 inline-flex h-11 items-center justify-center rounded-md bg-neutral-950 px-5 text-sm font-medium text-white">
          {loading ? <Loader2 className="h-4 w-4 animate-spin" /> : "Track"}
        </button>
      </form>

      {error && <p className="rounded-md bg-red-50 p-3 text-sm text-red-700">{error}</p>}
      {message && <p className="rounded-md bg-emerald-50 p-3 text-sm text-emerald-700">{message}</p>}

      {!data && !error && (
        <section className="rounded-md border border-[color:var(--brand-line)] bg-white p-6 text-sm leading-6 text-neutral-600">
          Enter your reference code and phone number to view your inquiry status. Payment upload will appear only after
          staff sends your quotation.
        </section>
      )}

      {data && (
        <div className="grid gap-6 xl:grid-cols-[1fr_0.9fr]">
          <section className="rounded-md border border-neutral-200 bg-white p-5">
            <p className="text-xs font-medium uppercase tracking-[0.18em] text-neutral-500">Inquiry</p>
            <div className="mt-3 flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
              <div>
                <h2 className="text-2xl font-semibold text-neutral-950">{data.inquiry.refCode}</h2>
                <p className="mt-1 text-sm text-neutral-500">{data.inquiry.eventType} for {data.inquiry.pax} pax</p>
              </div>
              <Status value={data.inquiry.status} />
            </div>
            <div className="mt-5 grid gap-3 sm:grid-cols-2">
              <Info label="Event date" value={data.inquiry.eventDate} />
              <Info label="Service time" value={data.inquiry.eventTime ?? "To confirm"} />
              <Info label="Venue" value={data.inquiry.venue || "To confirm"} />
              <Info label="Package" value={data.inquiry.package?.name ?? "To confirm"} />
            </div>
            <div className="mt-6">
              <h3 className="text-sm font-semibold text-neutral-950">Selections</h3>
              <div className="mt-3 grid gap-2">
                {data.inquiry.selections.length === 0 ? (
                  <p className="text-sm text-neutral-500">No menu selections yet.</p>
                ) : (
                  data.inquiry.selections.map((item) => (
                    <div key={`${item.item_type}-${item.snapshot_name}`} className="flex items-center justify-between rounded-md border border-neutral-200 px-3 py-2 text-sm">
                      <span className="capitalize">{item.item_type}: {item.snapshot_name}</span>
                      {Number(item.snapshot_price) > 0 && <span>{money(item.snapshot_price)}</span>}
                    </div>
                  ))
                )}
              </div>
            </div>
          </section>

          <section className="grid gap-6">
            <div className="rounded-md border border-neutral-200 bg-white p-5">
              <h2 className="text-lg font-semibold text-neutral-950">Quotation and payment</h2>
              {data.quotation ? (
                <div className="mt-4 space-y-3 text-sm">
                  <Info label="Quotation" value={`${data.quotation.quotation_number} (${data.quotation.status})`} />
                  <Info label="Total" value={money(data.quotation.total_amount)} />
                  <Info label="Reservation fee" value={money(data.quotation.reservation_fee_amount)} />
                  <Info label="Reservation fee due" value={data.quotation.reservation_fee_due_at ?? "To confirm"} />
                  <Info label="Final payment due" value={data.quotation.final_payment_due_at ?? "To confirm"} />
                  <div className="rounded-md border border-[color:var(--brand-line)] bg-[color:var(--brand-cream)] p-3">
                    <p className="text-sm font-semibold text-[color:var(--brand-maroon)]">{data.paymentInstructions?.title ?? "Payment instructions"}</p>
                    <div className="mt-3 grid gap-2">
                      {data.paymentInstructions?.gcashNumber && <Info label="GCash" value={`${data.paymentInstructions.gcashName ?? "Account"} - ${data.paymentInstructions.gcashNumber}`} />}
                      {data.paymentInstructions?.paymayaNumber && <Info label="Maya / PayMaya" value={`${data.paymentInstructions.paymayaName ?? "Account"} - ${data.paymentInstructions.paymayaNumber}`} />}
                      {data.paymentInstructions?.bankDetails && <Info label="Bank transfer" value={data.paymentInstructions.bankDetails} />}
                      <p className="text-sm leading-6 text-neutral-600">
                        {data.paymentInstructions?.notes ?? "GCash, Maya/PayMaya, bank transfer, and other Philippine payment apps are accepted for staff verification."}
                      </p>
                    </div>
                  </div>
                </div>
              ) : (
                <p className="mt-3 text-sm text-neutral-500">
                  No quotation has been sent yet. Staff will contact you if details need final confirmation before payment instructions are shown.
                </p>
              )}
              <div className="mt-5 space-y-2">
                {data.payments.map((payment) => (
                  <div key={`${payment.created_at}-${payment.amount}`} className="rounded-md border border-neutral-200 p-3 text-sm">
                    <div className="flex items-center justify-between">
                      <span>{money(payment.amount)} via {payment.payment_method}</span>
                      <Status value={payment.payment_status} />
                    </div>
                    {payment.rejected_reason && <p className="mt-2 text-red-700">{payment.rejected_reason}</p>}
                  </div>
                ))}
              </div>
            </div>

            {data.quotation && (
            <form onSubmit={(event) => submitForm(event, `/api/track/${encodeURIComponent(data.inquiry.refCode)}/payment`, "Payment proof uploaded for staff verification.")} className="rounded-md border border-neutral-200 bg-white p-5">
              <h2 className="text-lg font-semibold text-neutral-950">Send reservation fee proof</h2>
              <p className="mt-2 text-sm leading-6 text-neutral-600">
                Upload proof after sending the reservation fee. The date is reserved only after staff verifies the payment.
              </p>
              <div className="mt-4 grid gap-3 sm:grid-cols-2">
                <Field name="amount" label="Amount" type="number" required />
                <Field name="reference_number" label="Reference number" />
                <label className="text-sm font-medium text-neutral-800">
                  Method
                  <select name="method" className="mt-2 h-11 w-full rounded-md border border-neutral-300 px-3 text-sm">
                    <option value="gcash">GCash</option>
                    <option value="paymaya">Maya / PayMaya</option>
                    <option value="bank_transfer">Bank transfer</option>
                    <option value="other_ph_app">Other PH payment app</option>
                    <option value="other">Other</option>
                  </select>
                </label>
                <label className="text-sm font-medium text-neutral-800">
                  Proof
                  <input name="proof" type="file" className="mt-2 h-11 w-full rounded-md border border-neutral-300 px-3 py-2 text-sm" required />
                </label>
              </div>
              <button disabled={loading} className="mt-4 inline-flex h-10 items-center gap-2 rounded-md bg-neutral-950 px-4 text-sm font-medium text-white">
                <Upload className="h-4 w-4" />
                Upload proof
              </button>
              <button disabled type="button" className="ml-3 mt-4 inline-flex h-10 items-center rounded-md border border-neutral-300 px-4 text-sm font-medium text-neutral-400">
                Pay online with card (soon)
              </button>
            </form>
            )}
          </section>

          {data.inquiry.canEdit && (
            <form onSubmit={(event) => submitForm(event, `/api/track/${encodeURIComponent(data.inquiry.refCode)}/edit`, "Inquiry updated.")} className="rounded-md border border-neutral-200 bg-white p-5 xl:col-span-2">
              <h2 className="text-lg font-semibold text-neutral-950">Edit inquiry</h2>
              <p className="mt-1 text-sm text-neutral-500">Available until {data.inquiry.editUntil} while staff has not locked consultation.</p>
              <div className="mt-4 grid gap-3 sm:grid-cols-2">
                <Field name="venue_name" label="Venue name" />
                <Field name="venue_address" label="Venue address" />
              </div>
              <label className="mt-3 block text-sm font-medium text-neutral-800">
                Notes
                <textarea name="notes" defaultValue={data.inquiry.notes ?? ""} className="mt-2 min-h-24 w-full rounded-md border border-neutral-300 px-3 py-3 text-sm" />
              </label>
              <button disabled={loading} className="mt-4 h-10 rounded-md bg-neutral-950 px-4 text-sm font-medium text-white">Save edit</button>
            </form>
          )}

          {data.reservation?.status === "completed" && (
            <form onSubmit={(event) => submitForm(event, `/api/track/${encodeURIComponent(data.inquiry.refCode)}/feedback`, "Thank you for the feedback.")} className="rounded-md border border-neutral-200 bg-white p-5 xl:col-span-2">
              <h2 className="text-lg font-semibold text-neutral-950">Feedback</h2>
              <div className="mt-4 grid gap-3 sm:grid-cols-4">
                <Field name="rating" label="Overall" type="number" min="1" max="5" required />
                <Field name="food_quality_rating" label="Food" type="number" min="1" max="5" />
                <Field name="service_quality_rating" label="Service" type="number" min="1" max="5" />
                <Field name="overall_experience_rating" label="Experience" type="number" min="1" max="5" />
              </div>
              <label className="mt-3 block text-sm font-medium text-neutral-800">
                Comments
                <textarea name="comments" className="mt-2 min-h-24 w-full rounded-md border border-neutral-300 px-3 py-3 text-sm" />
              </label>
              <button disabled={loading} className="mt-4 inline-flex h-10 items-center gap-2 rounded-md bg-neutral-950 px-4 text-sm font-medium text-white">
                <CheckCircle2 className="h-4 w-4" />
                Submit feedback
              </button>
            </form>
          )}
        </div>
      )}
    </div>
  );
}

function Field({ label, name, type = "text", required, min, max }: { label: string; name: string; type?: string; required?: boolean; min?: string; max?: string }) {
  return (
    <label className="text-sm font-medium text-neutral-800">
      {label}
      <input name={name} type={type} required={required} min={min} max={max} className="mt-2 h-11 w-full rounded-md border border-neutral-300 px-3 text-sm" />
    </label>
  );
}

function Info({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <p className="text-xs uppercase tracking-[0.12em] text-neutral-500">{label}</p>
      <p className="mt-1 text-sm font-medium text-neutral-900">{value}</p>
    </div>
  );
}

function Status({ value }: { value: string }) {
  return (
    <span className="inline-flex rounded-full border border-neutral-300 px-2.5 py-1 text-xs font-medium capitalize text-neutral-700">
      {value.replaceAll("_", " ")}
    </span>
  );
}
