import { addDays, isValidPax } from "@/lib/catering";
import { logInternalNotification, sendCustomerEmail, sendCustomerSms } from "@/lib/notifications";
import { createServiceClient } from "@/lib/supabase-service";
import { readJsonArray, readNullableText, readNumber, readText } from "../infrastructure/form-data";

type InquiryUseCaseResult = { refCode: string } | { error: string; status: number };

export async function createInquiryFromForm(form: FormData): Promise<InquiryUseCaseResult> {
  const customerName = readText(form, "customer_name");
  const customerEmail = readText(form, "customer_email").toLowerCase();
  const customerPhone = readText(form, "customer_phone");
  const eventType = readText(form, "event_type");
  const eventDate = readText(form, "event_date");
  const pax = readNumber(form, "guest_count");
  const packageId = readText(form, "package_id");
  const dishIds = readJsonArray(form, "dish_ids");
  const drinkIds = readJsonArray(form, "drink_ids");
  const addonIds = readJsonArray(form, "addon_ids");
  const requirements = readJsonArray(form, "requirements");

  if (customerName.length < 2 || !customerPhone || !eventType || !eventDate || !pax) {
    return { error: "Please provide name, contact number, event type, date, and pax.", status: 422 };
  }

  if (customerEmail && !customerEmail.includes("@")) {
    return { error: "Please enter a valid email address or leave email blank.", status: 422 };
  }

  const supabase = createServiceClient();
  const { data: settings } = await supabase
    .from("business_settings")
    .select("minimum_booking_days, customer_edit_window_hours, business_name, business_phone")
    .limit(1)
    .single();

  const minimumBookingDays = Number(settings?.minimum_booking_days ?? 14);
  const requestedDate = new Date(`${eventDate}T00:00:00`);
  const minimumDate = addDays(new Date(), Math.max(0, minimumBookingDays - 1));
  minimumDate.setHours(0, 0, 0, 0);

  if (requestedDate < minimumDate) {
    return {
      error: `Online inquiries must be at least ${minimumBookingDays} days before the event. Please contact the office for urgent booking review.`,
      status: 422,
    };
  }

  const { data: packageRule, error: packageError } = await supabase
    .from("catering_packages")
    .select("id, name, price_per_pax, minimum_pax, pax_increment, meal_slots, drink_slots")
    .eq("id", packageId)
    .eq("is_active", true)
    .single();

  if (packageError || !packageRule) return { error: "Selected package is unavailable.", status: 422 };

  if (!isValidPax(pax, packageRule)) {
    return { error: `Pax must be at least ${packageRule.minimum_pax} and increase by ${packageRule.pax_increment}.`, status: 422 };
  }

  if (dishIds.length > packageRule.meal_slots || drinkIds.length > packageRule.drink_slots) {
    return { error: "Menu selections exceed the selected package slots.", status: 422 };
  }

  const [{ data: dishes }, { data: drinks }, { data: addons }] = await Promise.all([
    dishIds.length
      ? supabase.from("dishes").select("id, name, dish_type, premium_price").in("id", dishIds).eq("is_active", true).eq("status", "available")
      : Promise.resolve({ data: [] }),
    drinkIds.length
      ? supabase.from("drinks").select("id, name, is_premium, premium_price").in("id", drinkIds).eq("is_active", true).eq("status", "available")
      : Promise.resolve({ data: [] }),
    addonIds.length ? supabase.from("addons").select("id, name, price").in("id", addonIds).eq("is_active", true) : Promise.resolve({ data: [] }),
  ]);

  if ((dishes ?? []).length !== dishIds.length || (drinks ?? []).length !== drinkIds.length || (addons ?? []).length !== addonIds.length) {
    return { error: "One or more selected catalog items are unavailable.", status: 422 };
  }

  const { data: customer, error: customerError } = await supabase
    .from("customers")
    .insert({ full_name: customerName, contact_number: customerPhone, email: customerEmail || null })
    .select("id")
    .single();

  if (customerError || !customer) return { error: customerError?.message ?? "Could not create customer.", status: 400 };

  const editUntil = addDays(new Date(), 0);
  editUntil.setHours(editUntil.getHours() + Number(settings?.customer_edit_window_hours ?? 24));

  const { data: inquiry, error } = await supabase
    .from("inquiries")
    .insert({
      customer_id: customer.id,
      customer_name: customerName,
      customer_email: customerEmail || null,
      customer_phone: customerPhone,
      preferred_contact_method: readText(form, "preferred_contact_method") || "phone",
      lead_source: readText(form, "lead_source") || "website",
      event_type: eventType,
      event_date: eventDate,
      event_time: readNullableText(form, "event_time"),
      guest_count: pax,
      estimated_pax: pax,
      venue_name: readNullableText(form, "venue_name"),
      venue_address: readNullableText(form, "venue_address"),
      barangay: readNullableText(form, "barangay"),
      city: readNullableText(form, "city"),
      province: readNullableText(form, "province"),
      package_id: packageId,
      budget_min: readNumber(form, "budget_min"),
      budget_max: readNumber(form, "budget_max"),
      notes: readNullableText(form, "notes"),
      customer_notes: readNullableText(form, "notes"),
      customer_edit_until: editUntil.toISOString(),
      status: "new",
    })
    .select("id, ref_code, reference_code, customer_email, customer_name, customer_phone, event_type, event_date")
    .single();

  if (error || !inquiry) return { error: error?.message ?? "Could not create inquiry.", status: 400 };

  const refCode = inquiry.reference_code || inquiry.ref_code;
  if (!inquiry.reference_code && refCode) await supabase.from("inquiries").update({ reference_code: refCode }).eq("id", inquiry.id);

  const selections = [
    ...(dishes ?? []).map((item) => ({ inquiry_id: inquiry.id, item_type: "dish", item_id: item.id, snapshot_name: item.name, snapshot_price: Number(item.premium_price ?? 0) })),
    ...(drinks ?? []).map((item) => ({ inquiry_id: inquiry.id, item_type: "drink", item_id: item.id, snapshot_name: item.name, snapshot_price: Number(item.premium_price ?? 0) })),
    ...(addons ?? []).map((item) => ({ inquiry_id: inquiry.id, item_type: "addon", item_id: item.id, snapshot_name: item.name, snapshot_price: Number(item.price ?? 0) })),
  ];

  if (selections.length) await supabase.from("inquiry_menu_selections").insert(selections);

  if (requirements.length) {
    await supabase.from("inquiry_special_requirements").insert(requirements.map((item) => ({ inquiry_id: inquiry.id, requirement_type: item })));
  }

  await supabase.from("activity_logs").insert({
    inquiry_id: inquiry.id,
    action: "inquiry_created",
    description: `Customer submitted inquiry ${refCode}.`,
    new_values: { refCode, packageId, pax, dishIds, drinkIds, addonIds, requirements },
  });

  await logInternalNotification({
    inquiryId: inquiry.id,
    subject: `New inquiry ${refCode}`,
    body: `${inquiry.customer_name} requested a quote for ${inquiry.event_type}.`,
  });

  const trackingLink = `${process.env.NEXT_PUBLIC_APP_URL ?? "http://localhost:3000"}/track/${encodeURIComponent(refCode)}`;
  await sendCustomerEmail({
    to: inquiry.customer_email,
    inquiryId: inquiry.id,
    templateKey: "inquiry_received",
    subject: `Your Zek Catering inquiry ${refCode}`,
    body: [
      `Hi ${inquiry.customer_name},`,
      "",
      "Thank you for submitting your catering inquiry.",
      `Reference Code: ${refCode}`,
      `Event Date: ${inquiry.event_date}`,
      "",
      "This is not yet a confirmed reservation. Reservation is confirmed only after quotation acceptance and verified reservation fee payment.",
      "",
      `Track here: ${trackingLink}`,
      "",
      settings?.business_name ?? "Zek Catering",
    ].join("\n"),
  });

  await sendCustomerSms({
    to: inquiry.customer_phone,
    inquiryId: inquiry.id,
    templateKey: "inquiry_received_sms",
    body: `Hi ${inquiry.customer_name}, thank you for contacting ${settings?.business_name ?? "Zek Catering"}. Your reference code is ${refCode}. Track: ${trackingLink}. This is not yet confirmed.`,
  });

  return { refCode };
}
