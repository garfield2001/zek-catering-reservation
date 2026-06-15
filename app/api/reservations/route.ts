import { NextResponse } from "next/server";
import { getCurrentProfile } from "@/lib/auth";
import { sendCustomerEmail } from "@/lib/notifications";
import { createServiceClient } from "@/lib/supabase-service";

function readText(form: FormData, name: string) {
  return String(form.get(name) ?? "").trim();
}

function readNullableText(form: FormData, name: string) {
  const value = readText(form, name);
  return value || null;
}

function readNumber(form: FormData, name: string) {
  const value = Number(form.get(name) || 0);
  return Number.isFinite(value) ? value : 0;
}

export async function POST(request: Request) {
  const profile = await getCurrentProfile();

  if (!profile) {
    return NextResponse.json({ error: "Please sign in again before creating a reservation." }, { status: 401 });
  }

  const form = await request.formData();
  const subtotal = readNumber(form, "subtotal");
  const deliveryFee = readNumber(form, "delivery_fee");
  const discount = readNumber(form, "discount_amount");
  const inquiryId = readNullableText(form, "inquiry_id");
  const customerEmail = readText(form, "customer_email").toLowerCase();

  if (!readText(form, "customer_name") || !customerEmail || !readText(form, "event_date")) {
    return NextResponse.json({ error: "Customer name, email, and event date are required." }, { status: 422 });
  }

  const supabase = createServiceClient();
  const { data, error } = await supabase
    .from("reservations")
    .insert({
      inquiry_id: inquiryId,
      customer_name: readText(form, "customer_name"),
      customer_email: customerEmail,
      customer_phone: readNullableText(form, "customer_phone"),
      event_type: readText(form, "event_type"),
      event_date: readText(form, "event_date"),
      event_start_time: readNullableText(form, "event_start_time"),
      event_end_time: readNullableText(form, "event_end_time"),
      guest_count: Number(readText(form, "guest_count")),
      venue_name: readNullableText(form, "venue_name"),
      venue_address: readText(form, "venue_address"),
      barangay: readNullableText(form, "barangay"),
      city: readText(form, "city"),
      province: readText(form, "province") || "Metro Manila",
      package_id: readNullableText(form, "package_id"),
      service_style: readText(form, "service_style") || "buffet",
      status: readText(form, "status") || "draft",
      subtotal,
      delivery_fee: deliveryFee,
      discount_amount: discount,
      total_amount: subtotal + deliveryFee - discount,
      deposit_required: readNumber(form, "deposit_required"),
      internal_notes: readNullableText(form, "internal_notes"),
      customer_notes: readNullableText(form, "customer_notes"),
      assigned_to: profile.id,
      finalized_by: profile.id,
      finalized_at: new Date().toISOString(),
    })
    .select("id, reservation_code, customer_name, customer_email, event_type, event_date, total_amount, deposit_required, status")
    .single();

  if (error || !data) {
    return NextResponse.json({ error: error?.message ?? "Could not create reservation." }, { status: 400 });
  }

  if (inquiryId) {
    await supabase
      .from("inquiries")
      .update({ status: "converted", assigned_to: profile.id, updated_at: new Date().toISOString() })
      .eq("id", inquiryId);
  }

  if (form.get("email_customer") === "on") {
    await sendCustomerEmail({
      to: data.customer_email,
      reservationId: data.id,
      inquiryId,
      subject: `Zek Catering reservation ${data.reservation_code}`,
      body: [
        `Hi ${data.customer_name},`,
        "",
        `Your Zek Catering reservation draft is ready: ${data.reservation_code}.`,
        `Event: ${data.event_type}`,
        `Date: ${data.event_date}`,
        `Current status: ${data.status}`,
        `Estimated total: PHP ${Number(data.total_amount).toLocaleString()}`,
        `Deposit required: PHP ${Number(data.deposit_required).toLocaleString()}`,
        "",
        "Please reply to confirm details, payment instructions, or any changes before final confirmation.",
        "",
        "Zek Catering",
      ].join("\n"),
    });
  }

  return NextResponse.json({ reservationCode: data.reservation_code });
}
