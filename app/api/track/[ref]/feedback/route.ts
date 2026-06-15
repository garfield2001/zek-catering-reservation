import { NextResponse } from "next/server";
import { findInquiryForCustomer, normalizeContactNumber, normalizeReferenceCode } from "@/features/tracking/server/reference";

function readText(form: FormData, name: string) {
  return String(form.get(name) ?? "").trim();
}

function readRating(form: FormData, name: string) {
  const value = Number(form.get(name) ?? 0);
  return Number.isFinite(value) && value >= 1 && value <= 5 ? value : null;
}

type FeedbackInquiry = {
  id: string;
  customer_id: string | null;
  customer_phone: string;
} & Record<string, unknown>;

export async function POST(request: Request, { params }: { params: Promise<{ ref: string }> }) {
  const { ref } = await params;
  const form = await request.formData();
  const refCode = normalizeReferenceCode(ref);
  const contactNumber = normalizeContactNumber(form.get("contact_number"));
  const rating = readRating(form, "rating");

  if (!refCode || !contactNumber || !rating) {
    return NextResponse.json({ error: "A valid reference code, contact number, and rating are required." }, { status: 422 });
  }

  const { supabase, inquiry, error: inquiryError } = await findInquiryForCustomer<FeedbackInquiry>({
    refCode,
    contactNumber,
    select: "id, customer_id, customer_phone",
  });

  if (inquiryError) {
    return NextResponse.json({ error: inquiryError.message }, { status: 400 });
  }

  if (!inquiry) {
    return NextResponse.json({ error: "No inquiry matched that reference and contact number." }, { status: 404 });
  }

  const { data: reservation } = await supabase
    .from("reservations")
    .select("id, status")
    .eq("inquiry_id", inquiry.id)
    .eq("status", "completed")
    .maybeSingle();

  if (!reservation) {
    return NextResponse.json({ error: "Feedback opens after the event is marked completed." }, { status: 422 });
  }

  const { error } = await supabase.from("feedback").insert({
    reservation_id: reservation.id,
    inquiry_id: inquiry.id,
    customer_id: inquiry.customer_id,
    rating,
    food_quality_rating: readRating(form, "food_quality_rating"),
    service_quality_rating: readRating(form, "service_quality_rating"),
    overall_experience_rating: readRating(form, "overall_experience_rating"),
    comments: readText(form, "comments") || null,
  });

  if (error) {
    return NextResponse.json({ error: error.message }, { status: 400 });
  }

  await supabase.from("activity_logs").insert({
    inquiry_id: inquiry.id,
    reservation_id: reservation.id,
    action: "feedback_submitted",
    description: "Customer submitted feedback.",
    new_values: { rating },
  });

  return NextResponse.json({ ok: true });
}
