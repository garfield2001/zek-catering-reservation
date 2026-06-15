import { NextResponse } from "next/server";
import { createInquiryFromForm } from "@/features/inquiries/application/create-inquiry";

export async function POST(request: Request) {
  const result = await createInquiryFromForm(await request.formData());

  if ("error" in result) {
    return NextResponse.json({ error: result.error }, { status: result.status });
  }

  return NextResponse.json({ refCode: result.refCode });
}
