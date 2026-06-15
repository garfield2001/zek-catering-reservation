import { requireAdmin } from "@/lib/auth";
import { createClient } from "@/lib/server";

async function updateSettings(formData: FormData) {
  "use server";

  await requireAdmin();
  const supabase = await createClient();
  const id = String(formData.get("id") ?? "");
  const payload = {
    minimum_booking_days: Number(formData.get("minimum_booking_days") ?? 14),
    customer_edit_window_hours: Number(formData.get("customer_edit_window_hours") ?? 24),
    quotation_validity_days: Number(formData.get("quotation_validity_days") ?? 3),
    reservation_fee_type: String(formData.get("reservation_fee_type") ?? "fixed"),
    reservation_fee_value: Number(formData.get("reservation_fee_value") ?? 5000),
    reservation_fee_due_days: Number(formData.get("reservation_fee_due_days") ?? 3),
    final_payment_due_days: Number(formData.get("final_payment_due_days") ?? 7),
    reschedule_deadline_days: Number(formData.get("reschedule_deadline_days") ?? 14),
    payment_instruction_title: String(formData.get("payment_instruction_title") ?? ""),
    gcash_account_name: nullableText(formData.get("gcash_account_name")),
    gcash_account_number: nullableText(formData.get("gcash_account_number")),
    paymaya_account_name: nullableText(formData.get("paymaya_account_name")),
    paymaya_account_number: nullableText(formData.get("paymaya_account_number")),
    bank_account_details: nullableText(formData.get("bank_account_details")),
    payment_notes: nullableText(formData.get("payment_notes")),
  };

  if (id) {
    await supabase.from("business_settings").update(payload).eq("id", id);
  } else {
    await supabase.from("business_settings").insert(payload);
  }
}

export default async function AdminSettingsPage() {
  await requireAdmin();
  const supabase = await createClient();
  const { data: settings } = await supabase.from("business_settings").select("*").limit(1).single();

  return (
    <div className="space-y-6">
      <section className="rounded-md border border-neutral-200 bg-white p-6">
        <p className="text-xs font-medium uppercase tracking-[0.18em] text-neutral-500">Settings</p>
        <h1 className="mt-3 text-3xl font-semibold tracking-tight text-neutral-950">Booking policies</h1>
        <p className="mt-2 max-w-2xl text-sm leading-6 text-neutral-600">
          Policy values used by public inquiry, quotation deadlines, reservation fee defaults, final payment due dates, and notifications.
        </p>
      </section>

      <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <Metric label="Minimum booking days" value={`${settings?.minimum_booking_days ?? 14}`} />
        <Metric label="Customer edit window" value={`${settings?.customer_edit_window_hours ?? 24} hrs`} />
        <Metric label="Reservation fee" value={`${settings?.reservation_fee_type ?? "fixed"} ${settings?.reservation_fee_value ?? 5000}`} />
        <Metric label="Final due before event" value={`${settings?.final_payment_due_days ?? 7} days`} />
      </section>

      <form action={updateSettings} className="grid gap-6 rounded-md border border-neutral-200 bg-white p-6">
        <input type="hidden" name="id" value={settings?.id ?? ""} />
        <div>
          <h2 className="text-lg font-semibold text-neutral-950">Policy controls</h2>
          <p className="mt-2 text-sm text-neutral-600">These values guide inquiry validation, quotation expiry, reservation fee deadlines, final payment, and rescheduling rules.</p>
        </div>
        <div className="grid gap-4 md:grid-cols-3">
          <Field name="minimum_booking_days" label="Minimum booking days" type="number" defaultValue={settings?.minimum_booking_days ?? 14} />
          <Field name="customer_edit_window_hours" label="Customer edit window hours" type="number" defaultValue={settings?.customer_edit_window_hours ?? 24} />
          <Field name="quotation_validity_days" label="Quotation validity days" type="number" defaultValue={settings?.quotation_validity_days ?? 3} />
          <label className="text-sm font-medium text-neutral-800">
            Reservation fee type
            <select name="reservation_fee_type" defaultValue={settings?.reservation_fee_type ?? "fixed"} className="mt-2 h-11 w-full rounded-md border border-neutral-300 px-3 text-sm">
              <option value="fixed">Fixed amount</option>
              <option value="percentage">Percentage</option>
            </select>
          </label>
          <Field name="reservation_fee_value" label="Reservation fee value" type="number" defaultValue={settings?.reservation_fee_value ?? 5000} />
          <Field name="reservation_fee_due_days" label="Fee due after quote acceptance" type="number" defaultValue={settings?.reservation_fee_due_days ?? 3} />
          <Field name="final_payment_due_days" label="Final payment due before event" type="number" defaultValue={settings?.final_payment_due_days ?? 7} />
          <Field name="reschedule_deadline_days" label="Reschedule deadline days" type="number" defaultValue={settings?.reschedule_deadline_days ?? 14} />
        </div>
        <div className="border-t border-neutral-200 pt-5">
          <h2 className="text-lg font-semibold text-neutral-950">Customer payment instructions</h2>
          <p className="mt-2 text-sm text-neutral-600">Shown on tracking only after staff sends a quotation.</p>
        </div>
        <div className="grid gap-4 md:grid-cols-2">
          <Field name="payment_instruction_title" label="Payment section title" defaultValue={settings?.payment_instruction_title ?? "Reservation fee payment instructions"} />
          <Field name="gcash_account_name" label="GCash account name" defaultValue={settings?.gcash_account_name ?? ""} />
          <Field name="gcash_account_number" label="GCash number" defaultValue={settings?.gcash_account_number ?? ""} />
          <Field name="paymaya_account_name" label="Maya / PayMaya account name" defaultValue={settings?.paymaya_account_name ?? ""} />
          <Field name="paymaya_account_number" label="Maya / PayMaya number" defaultValue={settings?.paymaya_account_number ?? ""} />
          <label className="text-sm font-medium text-neutral-800 md:col-span-2">
            Bank details
            <textarea name="bank_account_details" defaultValue={settings?.bank_account_details ?? ""} rows={3} className="mt-2 w-full rounded-md border border-neutral-300 px-3 py-3 text-sm" />
          </label>
          <label className="text-sm font-medium text-neutral-800 md:col-span-2">
            Payment notes
            <textarea name="payment_notes" defaultValue={settings?.payment_notes ?? ""} rows={3} className="mt-2 w-full rounded-md border border-neutral-300 px-3 py-3 text-sm" />
          </label>
        </div>
        <button className="h-11 w-fit rounded-md bg-[color:var(--brand-maroon)] px-5 text-sm font-medium text-white hover:bg-[color:var(--brand-brown)]">Save settings</button>
      </form>

      <section className="grid gap-4 md:grid-cols-2">
        <Panel title="Reservation and cancellation policy" copy={settings?.cancellation_policy_text ?? "Reservation fee is non-refundable by default."} />
        <Panel title="Refund policy" copy={settings?.refund_policy_text ?? "Refunds are subject to management approval."} />
        <Panel title="Quotation validity" copy={`${settings?.quotation_validity_days ?? 3} days after sending/acceptance workflow.`} />
        <Panel title="Notifications" copy={`Email ${settings?.email_enabled ? "enabled" : "disabled"}; SMS ${settings?.sms_enabled ? "enabled" : "disabled"}.`} />
      </section>
    </div>
  );
}

function Field({ label, name, type = "text", defaultValue }: { label: string; name: string; type?: string; defaultValue?: string | number }) {
  return (
    <label className="text-sm font-medium text-neutral-800">
      {label}
      <input name={name} type={type} defaultValue={defaultValue} className="mt-2 h-11 w-full rounded-md border border-neutral-300 px-3 text-sm" />
    </label>
  );
}

function nullableText(value: FormDataEntryValue | null) {
  const text = String(value ?? "").trim();
  return text || null;
}

function Metric({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-md border border-neutral-200 bg-white p-5">
      <p className="text-sm text-neutral-500">{label}</p>
      <p className="mt-2 text-2xl font-semibold text-neutral-950">{value}</p>
    </div>
  );
}

function Panel({ title, copy }: { title: string; copy: string }) {
  return (
    <div className="rounded-md border border-neutral-200 bg-white p-5">
      <h2 className="text-lg font-semibold text-neutral-950">{title}</h2>
      <p className="mt-3 text-sm leading-6 text-neutral-600">{copy}</p>
    </div>
  );
}
