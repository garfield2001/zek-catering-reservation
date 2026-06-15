import { NextResponse } from "next/server";
import { findInquiryForCustomer, normalizeContactNumber, normalizeReferenceCode } from "@/features/tracking/server/reference";
import { sendCustomerEmail, sendCustomerSms } from "@/lib/notifications";

function readText(form: FormData, name: string) {
  return String(form.get(name) ?? "").trim();
}

const maxProofSize = 5 * 1024 * 1024;
const allowedProofTypes = new Map([
  ["image/jpeg", "jpg"],
  ["image/png", "png"],
  ["image/webp", "webp"],
  ["application/pdf", "pdf"],
]);

type PaymentInquiry = {
  id: string;
  ref_code: string;
  reference_code: string | null;
  customer_name: string;
  customer_email: string | null;
  customer_phone: string;
} & Record<string, unknown>;

export async function POST(request: Request, { params }: { params: Promise<{ ref: string }> }) {
  const { ref } = await params;
  const form = await request.formData();
  const refCode = normalizeReferenceCode(ref);
  const contactNumber = normalizeContactNumber(form.get("contact_number"));
  const amount = Number(form.get("amount") ?? 0);
  const method = readText(form, "method") || "gcash";
  const referenceNumber = readText(form, "reference_number");
  const proof = form.get("proof");

  if (!refCode || !contactNumber || !Number.isFinite(amount) || amount <= 0 || !(proof instanceof File) || proof.size === 0) {
    return NextResponse.json({ error: "Contact number, amount, and proof image/file are required." }, { status: 422 });
  }

  if (proof.size > maxProofSize) {
    return NextResponse.json({ error: "Payment proof must be 5MB or smaller." }, { status: 422 });
  }

  const extension = allowedProofTypes.get(proof.type);
  if (!extension) {
    return NextResponse.json({ error: "Payment proof must be a JPG, PNG, WebP, or PDF file." }, { status: 422 });
  }

  const { supabase, inquiry, error: inquiryError } = await findInquiryForCustomer<PaymentInquiry>({
    refCode,
    contactNumber,
    select: "id, ref_code, reference_code, customer_name, customer_email, customer_phone",
  });

  if (inquiryError) {
    return NextResponse.json({ error: inquiryError.message }, { status: 400 });
  }

  if (!inquiry) {
    return NextResponse.json({ error: "No inquiry matched that reference and contact number." }, { status: 404 });
  }

  const path = `${inquiry.reference_code || inquiry.ref_code}/${crypto.randomUUID()}.${extension}`;
  const { error: uploadError } = await supabase.storage.from("payment-proofs").upload(path, proof, {
    contentType: proof.type,
    upsert: false,
  });

  if (uploadError) {
    return NextResponse.json({ error: uploadError.message }, { status: 400 });
  }

  const { error } = await supabase.from("payments").insert({
    inquiry_id: inquiry.id,
    amount,
    payment_method: method,
    payment_status: "pending",
    payment_type: "reservation_fee",
    reference_number: referenceNumber || null,
    proof_url: path,
    uploaded_by_customer: true,
  });

  if (error) {
    return NextResponse.json({ error: error.message }, { status: 400 });
  }

  await supabase.from("activity_logs").insert({
    inquiry_id: inquiry.id,
    action: "payment_proof_uploaded",
    description: `Payment proof uploaded for ${inquiry.reference_code || inquiry.ref_code}.`,
    new_values: { amount, method, referenceNumber },
  });

  await sendCustomerEmail({
    to: inquiry.customer_email,
    inquiryId: inquiry.id,
    templateKey: "payment_proof_received",
    subject: `Payment proof received - ${inquiry.reference_code || inquiry.ref_code}`,
    body: `Hi ${inquiry.customer_name},\n\nWe received your payment proof for ${inquiry.reference_code || inquiry.ref_code}. Staff will verify it shortly.`,
  });

  await sendCustomerSms({
    to: inquiry.customer_phone,
    inquiryId: inquiry.id,
    templateKey: "payment_proof_received_sms",
    body: `Hi ${inquiry.customer_name}, we received your payment proof for ${inquiry.reference_code || inquiry.ref_code}. Staff will verify it shortly.`,
  });

  return NextResponse.json({ ok: true });
}
