import { ManagementPage } from "../management-page";
import { requireAdmin } from "@/lib/auth";

const staff = [
  { Name: "Nico Reyes", Role: "Event captain", Events: 6, Status: "Available" },
  { Name: "Ari Lim", Role: "Sales lead", Events: 8, Status: "Booked" },
  { Name: "Sam Cruz", Role: "Kitchen lead", Events: 5, Status: "Available" },
  { Name: "Lena Yu", Role: "Service staff", Events: 4, Status: "On call" },
];

export default async function AdminStaffPage() {
  await requireAdmin();

  return (
    <ManagementPage
      eyebrow="Staff"
      title="Staff scheduling"
      description="Assign event owners, service captains, kitchen leads, and on-call staff by availability."
      metrics={[
        { label: "Active staff", value: "16" },
        { label: "Assigned this week", value: "9" },
        { label: "On call", value: "4" },
      ]}
      columns={["Name", "Role", "Events", "Status"]}
      rows={staff}
    />
  );
}
