import { redirect } from "next/navigation";
import { addDays } from "@/lib/catering";
import { requireProfile } from "@/lib/auth";
import { createClient } from "@/lib/server";

async function createManualInquiry(formData: FormData) {
  "use server";

  const profile = await requireProfile();
  const supabase = await createClient();
  const customerName = String(formData.get("customer_name") ?? "").trim();
  const customerPhone = String(formData.get("customer_phone") ?? "").trim();
  const customerEmail = String(formData.get("customer_email") ?? "").trim().toLowerCase();
  const eventDate = String(formData.get("event_date") ?? "").trim();
  const eventType = String(formData.get("event_type") ?? "").trim();

  if (!customerName || !customerPhone || !eventDate || !eventType) {
    redirect("/admin/inquiries/new?error=missing");
  }

  const { data: customer } = await supabase
    .from("customers")
    .insert({ full_name: customerName, contact_number: customerPhone, email: customerEmail || null })
    .select("id")
    .single();

  const editUntil = addDays(new Date(), 0);
  editUntil.setHours(editUntil.getHours() + 24);

  const { data, error } = await supabase
    .from("inquiries")
    .insert({
      customer_id: customer?.id ?? null,
      customer_name: customerName,
      customer_phone: customerPhone,
      customer_email: customerEmail || "no-email@zek.local",
      event_type: eventType,
      event_date: eventDate,
      event_time: String(formData.get("event_time") ?? "") || null,
      guest_count: Number(formData.get("guest_count") ?? 0) || null,
      estimated_pax: Number(formData.get("guest_count") ?? 0) || null,
      package_id: String(formData.get("package_id") ?? "") || null,
      venue_name: String(formData.get("venue_name") ?? "") || null,
      venue_address: String(formData.get("venue_address") ?? "") || null,
      city: String(formData.get("city") ?? "") || null,
      lead_source: String(formData.get("lead_source") ?? "phone"),
      status: "new",
      customer_notes: String(formData.get("notes") ?? "") || null,
      notes: String(formData.get("notes") ?? "") || null,
      customer_edit_until: editUntil.toISOString(),
      created_by: profile.id,
    })
    .select("id, ref_code")
    .single();

  if (error || !data) {
    redirect(`/admin/inquiries/new?error=${encodeURIComponent(error?.message ?? "create-failed")}`);
  }

  await supabase.from("inquiries").update({ reference_code: data.ref_code }).eq("id", data.id);
  await supabase.from("activity_logs").insert({
    inquiry_id: data.id,
    actor_id: profile.id,
    action: "manual_inquiry_created",
    description: `Staff created inquiry ${data.ref_code}.`,
  });

  redirect(`/admin/inquiries/${data.id}`);
}

export default async function AdminNewInquiryPage() {
  const supabase = await createClient();
  const { data: packages } = await supabase
    .from("catering_packages")
    .select("id, name, minimum_pax")
    .eq("is_active", true)
    .order("sort_order");

  return (
    <div className="space-y-6">
      <section className="rounded-md border border-neutral-200 bg-white p-6">
        <p className="text-xs font-medium uppercase tracking-[0.18em] text-neutral-500">Manual inquiry</p>
        <h1 className="mt-3 text-3xl font-semibold tracking-tight text-neutral-950">Create phone, SMS, Messenger, or walk-in lead</h1>
        <p className="mt-2 text-sm text-neutral-600">Staff can enter urgent inquiries here. Emergency/rush fees are added later in quotation.</p>
      </section>

      <form action={createManualInquiry} className="grid gap-5 rounded-md border border-neutral-200 bg-white p-6">
        <div className="grid gap-4 sm:grid-cols-2">
          <Field name="customer_name" label="Customer name" required />
          <Field name="customer_phone" label="Contact number" required />
          <Field name="customer_email" label="Email" type="email" />
          <label className="text-sm font-medium text-neutral-800">
            Lead source
            <select name="lead_source" className="mt-2 h-11 w-full rounded-md border border-neutral-300 px-3 text-sm">
              <option value="phone">Phone</option>
              <option value="sms">SMS</option>
              <option value="messenger">Messenger</option>
              <option value="walk_in">Walk-in</option>
              <option value="referral">Referral</option>
              <option value="website">Website</option>
            </select>
          </label>
          <Field name="event_type" label="Event type" required />
          <Field name="event_date" label="Event date" type="date" required />
          <Field name="event_time" label="Preferred time" type="time" />
          <Field name="guest_count" label="Estimated pax" type="number" />
        </div>
        <label className="text-sm font-medium text-neutral-800">
          Package
          <select name="package_id" className="mt-2 h-11 w-full rounded-md border border-neutral-300 px-3 text-sm">
            <option value="">Still deciding</option>
            {(packages ?? []).map((item) => (
              <option key={item.id} value={item.id}>{item.name} - min {item.minimum_pax}</option>
            ))}
          </select>
        </label>
        <div className="grid gap-4 sm:grid-cols-3">
          <Field name="venue_name" label="Venue name" />
          <Field name="venue_address" label="Venue address" />
          <Field name="city" label="City" />
        </div>
        <label className="text-sm font-medium text-neutral-800">
          Notes
          <textarea name="notes" className="mt-2 min-h-28 w-full rounded-md border border-neutral-300 px-3 py-3 text-sm" />
        </label>
        <button className="h-11 rounded-md bg-neutral-950 px-5 text-sm font-medium text-white">Create inquiry</button>
      </form>
    </div>
  );
}

function Field({ label, name, type = "text", required }: { label: string; name: string; type?: string; required?: boolean }) {
  return (
    <label className="text-sm font-medium text-neutral-800">
      {label}
      <input name={name} type={type} required={required} className="mt-2 h-11 w-full rounded-md border border-neutral-300 px-3 text-sm" />
    </label>
  );
}
