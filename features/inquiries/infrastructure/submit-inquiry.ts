import type { InquiryResult, InquirySelection } from "../domain/types";
import type { PackageRule } from "@/lib/catering";

export async function submitInquiry({
  form,
  packageRule,
  pax,
  selection,
}: {
  form: HTMLFormElement;
  packageRule: PackageRule;
  pax: number;
  selection: InquirySelection;
}): Promise<InquiryResult> {
  const formData = new FormData(form);
  formData.set("package_id", packageRule.id);
  formData.set("guest_count", String(pax));
  formData.set("dish_ids", JSON.stringify(selection.dishIds));
  formData.set("drink_ids", JSON.stringify(selection.drinkIds));
  formData.set("addon_ids", JSON.stringify(selection.addonIds));
  formData.set("requirements", JSON.stringify(selection.requirements));

  const response = await fetch("/api/inquiries", { method: "POST", body: formData });
  const payload = (await response.json().catch(() => ({}))) as { refCode?: string; error?: string };

  if (!response.ok || !payload.refCode) {
    throw new Error(payload.error ?? "Could not submit the inquiry. Please review the form and try again.");
  }

  return { refCode: payload.refCode };
}
