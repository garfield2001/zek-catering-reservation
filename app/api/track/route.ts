import { NextResponse } from "next/server";
import { money } from "@/lib/catering";
import { findInquiryForCustomer, normalizeContactNumber, normalizeReferenceCode } from "@/features/tracking/server/reference";

type TrackedInquiry = {
  id: string;
  ref_code: string;
  reference_code: string | null;
  customer_name: string;
  customer_phone: string;
  event_type: string;
  event_date: string | null;
  event_time: string | null;
  venue_name: string | null;
  venue_address: string | null;
  city: string | null;
  status: string;
  guest_count: number;
  customer_notes: string | null;
  customer_edit_until: string | null;
  consultation_locked_at: string | null;
  created_at: string;
  catering_packages: unknown;
  inquiry_menu_selections: unknown;
  inquiry_special_requirements: unknown;
} & Record<string, unknown>;

export async function POST(request: Request) {
  const body = (await request.json().catch(() => null)) as { refCode?: string; contactNumber?: string } | null;
  const refCode = normalizeReferenceCode(body?.refCode);
  const contactNumber = normalizeContactNumber(body?.contactNumber);

  if (!refCode || !contactNumber) {
    return NextResponse.json({ error: "A valid reference code and contact number are required." }, { status: 422 });
  }

  const { supabase, inquiry, error: inquiryError } = await findInquiryForCustomer<TrackedInquiry>({
    refCode,
    contactNumber,
    select:
      `
      id,
      ref_code,
      reference_code,
      customer_name,
      customer_phone,
      event_type,
      event_date,
      event_time,
      venue_name,
      venue_address,
      city,
      status,
      guest_count,
      customer_notes,
      customer_edit_until,
      consultation_locked_at,
      created_at,
      catering_packages(name, price_per_pax, meal_slots, drink_slots),
      inquiry_menu_selections(item_type, snapshot_name, snapshot_price, quantity),
      inquiry_special_requirements(requirement_type, notes)
    `,
  });

  if (inquiryError) {
    return NextResponse.json({ error: inquiryError.message }, { status: 400 });
  }

  if (!inquiry) {
    return NextResponse.json({ error: "No inquiry matched that reference and contact number." }, { status: 404 });
  }

  const [{ data: quotation }, { data: payments }, { data: reservation }, { data: settings }] = await Promise.all([
    supabase
      .from("quotations")
      .select("id, quotation_number, status, total_amount, reservation_fee_amount, reservation_fee_due_at, final_payment_due_at, valid_until, quotation_items(line_type, description, quantity, unit_price, amount, customer_visible_reason), quotation_menu_selections(selection_type, item_name_snapshot, premium_amount_snapshot)")
      .eq("inquiry_id", inquiry.id)
      .order("created_at", { ascending: false })
      .limit(1)
      .maybeSingle(),
    supabase
      .from("payments")
      .select("id, amount, payment_method, payment_status, payment_type, reference_number, uploaded_by_customer, verified_at, rejected_reason, created_at")
      .eq("inquiry_id", inquiry.id)
      .order("created_at", { ascending: false }),
    supabase
      .from("reservations")
      .select("id, reservation_code, status, event_date, final_pax, final_payment_due_at, final_payment_status")
      .eq("inquiry_id", inquiry.id)
      .maybeSingle(),
    supabase
      .from("business_settings")
      .select("payment_instruction_title, gcash_account_name, gcash_account_number, paymaya_account_name, paymaya_account_number, bank_account_details, payment_notes")
      .limit(1)
      .maybeSingle(),
  ]);

  const editUntil = inquiry.customer_edit_until ? new Date(inquiry.customer_edit_until) : null;
  const canEdit = !inquiry.consultation_locked_at && ["new", "new_inquiry"].includes(inquiry.status) && !!editUntil && editUntil > new Date();

  return NextResponse.json({
    inquiry: {
      id: inquiry.id,
      refCode: inquiry.reference_code || inquiry.ref_code,
      customerName: inquiry.customer_name,
      eventType: inquiry.event_type,
      eventDate: inquiry.event_date,
      eventTime: inquiry.event_time,
      venue: [inquiry.venue_name, inquiry.venue_address, inquiry.city].filter(Boolean).join(", "),
      status: inquiry.status,
      pax: inquiry.guest_count,
      notes: inquiry.customer_notes,
      canEdit,
      editUntil: inquiry.customer_edit_until,
      package: inquiry.catering_packages,
      selections: inquiry.inquiry_menu_selections,
      requirements: inquiry.inquiry_special_requirements,
    },
    quotation,
    payments,
    reservation,
    paymentInstructions: quotation
      ? {
          title: settings?.payment_instruction_title ?? "Reservation fee payment instructions",
          gcashName: settings?.gcash_account_name ?? null,
          gcashNumber: settings?.gcash_account_number ?? null,
          paymayaName: settings?.paymaya_account_name ?? null,
          paymayaNumber: settings?.paymaya_account_number ?? null,
          bankDetails: settings?.bank_account_details ?? null,
          notes: settings?.payment_notes ?? null,
        }
      : null,
    balanceSummary: quotation
      ? {
          total: money(quotation.total_amount),
          reservationFee: money(quotation.reservation_fee_amount),
        }
      : null,
  });
}
