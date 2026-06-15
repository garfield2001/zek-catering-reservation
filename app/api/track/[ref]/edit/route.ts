import { NextResponse } from "next/server";
import { findInquiryForCustomer, normalizeContactNumber, normalizeReferenceCode } from "@/features/tracking/server/reference";

function readText(form: FormData, name: string) {
  return String(form.get(name) ?? "").trim();
}

type EditableInquiry = {
  id: string;
  ref_code: string;
  reference_code: string | null;
  customer_phone: string;
  status: string;
  customer_edit_until: string | null;
  consultation_locked_at: string | null;
} & Record<string, unknown>;

export async function POST(request: Request, { params }: { params: Promise<{ ref: string }> }) {
  const { ref } = await params;
  const form = await request.formData();
  const refCode = normalizeReferenceCode(ref);
  const contactNumber = normalizeContactNumber(form.get("contact_number"));
  const notes = readText(form, "notes");
  const venueName = readText(form, "venue_name");
  const venueAddress = readText(form, "venue_address");

  if (!refCode || !contactNumber) {
    return NextResponse.json({ error: "A valid reference code and contact number are required." }, { status: 422 });
  }

  const { supabase, inquiry, error: inquiryError } = await findInquiryForCustomer<EditableInquiry>({
    refCode,
    contactNumber,
    select: "id, ref_code, reference_code, customer_phone, status, customer_edit_until, consultation_locked_at",
  });

  if (inquiryError) {
    return NextResponse.json({ error: inquiryError.message }, { status: 400 });
  }

  if (!inquiry) {
    return NextResponse.json({ error: "No inquiry matched that reference and contact number." }, { status: 404 });
  }

  const editUntil = inquiry.customer_edit_until ? new Date(inquiry.customer_edit_until) : null;
  const canEdit = !inquiry.consultation_locked_at && ["new", "new_inquiry"].includes(inquiry.status) && !!editUntil && editUntil > new Date();

  if (!canEdit) {
    return NextResponse.json({ error: "This inquiry is no longer editable by the customer." }, { status: 403 });
  }

  const { error } = await supabase
    .from("inquiries")
    .update({
      customer_notes: notes || null,
      notes: notes || null,
      venue_name: venueName || null,
      venue_address: venueAddress || null,
      updated_at: new Date().toISOString(),
    })
    .eq("id", inquiry.id);

  if (error) {
    return NextResponse.json({ error: error.message }, { status: 400 });
  }

  await supabase.from("activity_logs").insert({
    inquiry_id: inquiry.id,
    action: "customer_inquiry_edit",
    description: `Customer edited inquiry ${inquiry.reference_code || inquiry.ref_code}.`,
    new_values: { notes, venueName, venueAddress },
  });

  return NextResponse.json({ ok: true });
}
