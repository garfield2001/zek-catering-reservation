import { createServiceClient } from "@/lib/supabase-service";

type LookupError = { message: string };
type LookupResult<TInquiry> = { data: TInquiry | null; error: LookupError | null };
type LookupBuilder<TInquiry> = {
  select: (columns: string) => {
    eq: (column: string, value: string) => {
      eq: (column: string, value: string) => {
        maybeSingle: () => Promise<LookupResult<TInquiry>>;
      };
    };
  };
};

export function normalizeReferenceCode(value: unknown) {
  const refCode = String(value ?? "").trim().toUpperCase();
  return /^REF-\d{4}-\d{6}$/.test(refCode) ? refCode : null;
}

export function normalizeContactNumber(value: unknown) {
  return String(value ?? "").trim();
}

export async function findInquiryForCustomer<TInquiry extends Record<string, unknown>>({
  refCode,
  contactNumber,
  select,
}: {
  refCode: string;
  contactNumber: string;
  select: string;
}) {
  const supabase = createServiceClient();
  const inquiries = supabase.from("inquiries") as unknown as LookupBuilder<TInquiry>;

  const byReferenceCode = await inquiries
    .select(select)
    .eq("reference_code", refCode)
    .eq("customer_phone", contactNumber)
    .maybeSingle();

  if (byReferenceCode.data || byReferenceCode.error) {
    return { supabase, inquiry: byReferenceCode.data, error: byReferenceCode.error };
  }

  const byRefCode = await inquiries
    .select(select)
    .eq("ref_code", refCode)
    .eq("customer_phone", contactNumber)
    .maybeSingle();

  return { supabase, inquiry: byRefCode.data, error: byRefCode.error };
}
