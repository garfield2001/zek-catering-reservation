"use client";

import { FormEvent, Suspense, useEffect, useState } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import { createClient } from "@/lib/client";

type PackageOption = { id: string; name: string };

type InquiryDraft = {
  id: string;
  ref_code: string;
  customer_name: string;
  customer_email: string;
  customer_phone: string | null;
  event_type: string;
  event_date: string | null;
  event_time: string | null;
  guest_count: number | null;
  venue_name: string | null;
  venue_address: string | null;
  barangay: string | null;
  city: string | null;
  province: string | null;
  package_id: string | null;
  notes: string | null;
};

export default function NewReservationPage() {
  return (
    <Suspense fallback={null}>
      <NewReservationForm />
    </Suspense>
  );
}

function NewReservationForm() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const inquiryRef = searchParams.get("inquiry");
  const [packages, setPackages] = useState<PackageOption[]>([]);
  const [inquiry, setInquiry] = useState<InquiryDraft | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    const supabase = createClient();

    supabase
      .from("catering_packages")
      .select("id, name")
      .eq("is_active", true)
      .order("sort_order")
      .then(({ data }) => setPackages(data ?? []));

    if (!inquiryRef) {
      return;
    }

    supabase
      .from("inquiries")
      .select(
        "id, ref_code, customer_name, customer_email, customer_phone, event_type, event_date, event_time, guest_count, venue_name, venue_address, barangay, city, province, package_id, notes"
      )
      .eq("ref_code", inquiryRef)
      .single()
      .then(({ data, error: fetchError }) => {
        setInquiry((data as InquiryDraft | null) ?? null);
        if (fetchError) {
          setError("Could not load that inquiry. You can still create the reservation manually.");
        }
      });
  }, [inquiryRef]);

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setLoading(true);
    setError(null);

    const form = new FormData(event.currentTarget);
    const response = await fetch("/api/reservations", {
      method: "POST",
      body: form,
    });
    const result = (await response.json()) as { reservationCode?: string; error?: string };

    setLoading(false);

    if (!response.ok || !result.reservationCode) {
      setError(result.error ?? "Could not create reservation.");
      return;
    }

    router.push(`/admin/reservations?created=${result.reservationCode}`);
    router.refresh();
  }

  return (
    <div className="space-y-6">
      <section className="rounded-md border border-neutral-200 bg-white p-6">
        <p className="text-xs font-medium uppercase tracking-[0.18em] text-neutral-500">Final reservation</p>
        <h1 className="mt-3 text-3xl font-semibold tracking-tight text-neutral-950">Create reservation</h1>
        <p className="mt-2 max-w-2xl text-sm leading-6 text-neutral-600">
          Use this after the call or conversation when the date, headcount, package, venue, and payment plan are clear enough to reserve.
        </p>
        {inquiry && (
          <div className="mt-5 rounded-md border border-neutral-200 bg-neutral-50 p-4 text-sm text-neutral-700">
            Converting inquiry <span className="font-medium text-neutral-950">{inquiry.ref_code}</span> from{" "}
            <span className="font-medium text-neutral-950">{inquiry.customer_name}</span>.
          </div>
        )}
      </section>

      <form key={inquiry?.id ?? "manual"} onSubmit={handleSubmit} className="grid gap-5 rounded-md border border-neutral-200 bg-white p-6">
        {inquiry && <input type="hidden" name="inquiry_id" value={inquiry.id} />}
        <div className="grid gap-4 md:grid-cols-3">
          <Field name="customer_name" label="Customer name" required defaultValue={inquiry?.customer_name} />
          <Field name="customer_email" label="Customer email" type="email" required defaultValue={inquiry?.customer_email} />
          <Field name="customer_phone" label="Customer phone" defaultValue={inquiry?.customer_phone} />
          <Field name="event_type" label="Event type" required defaultValue={inquiry?.event_type} />
          <Field name="event_date" label="Event date" type="date" required defaultValue={inquiry?.event_date} />
          <Field name="guest_count" label="Guest count" type="number" min="1" required defaultValue={inquiry?.guest_count} />
          <Field name="event_start_time" label="Start time" type="time" defaultValue={inquiry?.event_time} />
          <Field name="event_end_time" label="End time" type="time" />
          <label className="text-sm font-medium text-neutral-800">
            Status
            <select
              name="status"
              defaultValue="tentative"
              className="mt-2 h-11 w-full rounded-md border border-neutral-300 px-3 text-sm outline-none focus:border-neutral-950"
            >
              <option value="draft">Draft</option>
              <option value="tentative">Tentative</option>
              <option value="confirmed">Confirmed</option>
            </select>
          </label>
        </div>
        <div className="grid gap-4 md:grid-cols-2">
          <Field name="venue_name" label="Venue name" defaultValue={inquiry?.venue_name} />
          <Field name="venue_address" label="Venue address" required defaultValue={inquiry?.venue_address} />
          <Field name="barangay" label="Barangay" defaultValue={inquiry?.barangay} />
          <Field name="city" label="City" required defaultValue={inquiry?.city} />
          <Field name="province" label="Province" defaultValue={inquiry?.province ?? "Metro Manila"} />
        </div>
        <div className="grid gap-4 md:grid-cols-3">
          <label className="text-sm font-medium text-neutral-800">
            Package
            <select
              name="package_id"
              defaultValue={inquiry?.package_id ?? ""}
              className="mt-2 h-11 w-full rounded-md border border-neutral-300 px-3 text-sm outline-none focus:border-neutral-950"
            >
              <option value="">Custom</option>
              {packages.map((item) => (
                <option key={item.id} value={item.id}>
                  {item.name}
                </option>
              ))}
            </select>
          </label>
          <label className="text-sm font-medium text-neutral-800">
            Service style
            <select name="service_style" className="mt-2 h-11 w-full rounded-md border border-neutral-300 px-3 text-sm outline-none focus:border-neutral-950">
              <option value="buffet">Buffet</option>
              <option value="plated">Plated</option>
              <option value="grazing">Grazing</option>
              <option value="custom">Custom</option>
            </select>
          </label>
          <Field name="deposit_required" label="Deposit required" type="number" min="0" />
        </div>
        <div className="grid gap-4 md:grid-cols-3">
          <Field name="subtotal" label="Subtotal" type="number" min="0" />
          <Field name="delivery_fee" label="Delivery fee" type="number" min="0" />
          <Field name="discount_amount" label="Discount" type="number" min="0" />
        </div>
        <label className="text-sm font-medium text-neutral-800">
          Internal notes
          <textarea
            name="internal_notes"
            defaultValue={inquiry?.notes ?? ""}
            className="mt-2 min-h-28 w-full rounded-md border border-neutral-300 px-3 py-3 text-sm outline-none focus:border-neutral-950"
          />
        </label>
        <label className="text-sm font-medium text-neutral-800">
          Customer-facing notes
          <textarea name="customer_notes" className="mt-2 min-h-28 w-full rounded-md border border-neutral-300 px-3 py-3 text-sm outline-none focus:border-neutral-950" />
        </label>
        <label className="flex items-center gap-3 rounded-md border border-neutral-200 bg-neutral-50 p-4 text-sm font-medium text-neutral-800">
          <input name="email_customer" type="checkbox" defaultChecked className="h-4 w-4 rounded border-neutral-300" />
          Email the customer a development reservation summary
        </label>
        {error && <p className="rounded-md bg-neutral-100 p-3 text-sm text-neutral-700">{error}</p>}
        <button disabled={loading} className="h-11 rounded-md bg-neutral-950 px-5 text-sm font-medium text-white hover:bg-neutral-800 disabled:opacity-60">
          {loading ? "Creating..." : "Create reservation and notify"}
        </button>
      </form>
    </div>
  );
}

function Field({
  label,
  name,
  type = "text",
  required,
  min,
  defaultValue,
}: {
  label: string;
  name: string;
  type?: string;
  required?: boolean;
  min?: string;
  defaultValue?: string | number | null;
}) {
  return (
    <label className="text-sm font-medium text-neutral-800">
      {label}
      <input
        name={name}
        type={type}
        required={required}
        min={min}
        defaultValue={defaultValue ?? ""}
        className="mt-2 h-11 w-full rounded-md border border-neutral-300 px-3 text-sm outline-none focus:border-neutral-950"
      />
    </label>
  );
}
