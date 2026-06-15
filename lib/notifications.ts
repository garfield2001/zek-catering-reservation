import { createServiceClient } from "@/lib/supabase-service";

type EmailPayload = {
  to: string | null | undefined;
  subject: string;
  body: string;
  templateKey?: string;
  inquiryId?: string | null;
  reservationId?: string | null;
};

type SmsPayload = {
  to: string;
  body: string;
  templateKey?: string;
  inquiryId?: string | null;
};

export async function sendCustomerEmail({
  to,
  subject,
  body,
  templateKey = "custom_email",
  inquiryId = null,
  reservationId = null,
}: EmailPayload) {
  if (!to) {
    return { status: "skipped" as const, providerReference: "missing-recipient" };
  }

  const supabase = createServiceClient();
  let status: "sent" | "failed" = "sent";
  let providerReference = "dev-email-log";

  const resendKey = process.env.RESEND_API_KEY;
  const from = process.env.RESERVATION_EMAIL_FROM ?? "Zek Catering <onboarding@resend.dev>";

  if (resendKey) {
    try {
      const response = await fetch("https://api.resend.com/emails", {
        method: "POST",
        headers: {
          Authorization: `Bearer ${resendKey}`,
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          from,
          to,
          subject,
          text: body,
        }),
      });

      const result = (await response.json().catch(() => null)) as { id?: string; message?: string } | null;

      if (!response.ok) {
        status = "failed";
        providerReference = result?.message ?? `resend-${response.status}`;
      } else {
        providerReference = result?.id ?? "resend";
      }
    } catch (error) {
      status = "failed";
      providerReference = error instanceof Error ? error.message : "email-send-failed";
    }
  }

  await supabase.from("notification_logs").insert({
    inquiry_id: inquiryId,
    reservation_id: reservationId,
    channel: "email",
    recipient: to,
    subject,
    body,
    status,
    provider_reference: providerReference,
    sent_at: status === "sent" ? new Date().toISOString() : null,
  });

  await supabase.from("notifications").insert({
    inquiry_id: inquiryId,
    channel: "email",
    recipient: to,
    template_key: templateKey,
    subject,
    body,
    status,
    provider: resendKey ? "resend" : "development",
    provider_message_id: status === "sent" ? providerReference : null,
    error_message: status === "failed" ? providerReference : null,
    sent_at: status === "sent" ? new Date().toISOString() : null,
  });

  return { status, providerReference };
}

export async function sendCustomerSms({ to, body, templateKey = "custom_sms", inquiryId = null }: SmsPayload) {
  const supabase = createServiceClient();
  let status: "sent" | "failed" = "sent";
  let providerReference = "dev-sms-log";

  const smsEnabled = process.env.ENABLE_SMS !== "false";
  const accountSid = process.env.TWILIO_ACCOUNT_SID;
  const authToken = process.env.TWILIO_AUTH_TOKEN;
  const from = process.env.TWILIO_PHONE_NUMBER;

  if (smsEnabled && accountSid && authToken && from) {
    try {
      const response = await fetch(`https://api.twilio.com/2010-04-01/Accounts/${accountSid}/Messages.json`, {
        method: "POST",
        headers: {
          Authorization: `Basic ${Buffer.from(`${accountSid}:${authToken}`).toString("base64")}`,
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: new URLSearchParams({ From: from, To: to, Body: body }),
      });

      const result = (await response.json().catch(() => null)) as { sid?: string; message?: string } | null;
      if (!response.ok) {
        status = "failed";
        providerReference = result?.message ?? `twilio-${response.status}`;
      } else {
        providerReference = result?.sid ?? "twilio";
      }
    } catch (error) {
      status = "failed";
      providerReference = error instanceof Error ? error.message : "sms-send-failed";
    }
  }

  await supabase.from("notification_logs").insert({
    inquiry_id: inquiryId,
    channel: "sms",
    recipient: to,
    subject: null,
    body,
    status,
    provider_reference: providerReference,
    sent_at: status === "sent" ? new Date().toISOString() : null,
  });

  await supabase.from("notifications").insert({
    inquiry_id: inquiryId,
    channel: "sms",
    recipient: to,
    template_key: templateKey,
    body,
    status,
    provider: accountSid ? "twilio" : "development",
    provider_message_id: status === "sent" ? providerReference : null,
    error_message: status === "failed" ? providerReference : null,
    sent_at: status === "sent" ? new Date().toISOString() : null,
  });

  return { status, providerReference };
}

export async function logInternalNotification({
  inquiryId = null,
  reservationId = null,
  subject,
  body,
}: {
  inquiryId?: string | null;
  reservationId?: string | null;
  subject: string;
  body: string;
}) {
  const supabase = createServiceClient();

  await supabase.from("notification_logs").insert({
    inquiry_id: inquiryId,
    reservation_id: reservationId,
    channel: "system",
    recipient: "admin_staff",
    subject,
    body,
    status: "sent",
    provider_reference: "admin-workspace",
    sent_at: new Date().toISOString(),
  });
}
