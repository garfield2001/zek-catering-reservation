import { ManagementPage } from "../management-page";
import { inventory } from "@/lib/site-data";
import { requireAdmin } from "@/lib/auth";

export default async function AdminInventoryPage() {
  await requireAdmin();

  return (
    <ManagementPage
      eyebrow="Inventory"
      title="Service inventory"
      description="Track catering equipment availability against upcoming reservations before confirming large events."
      metrics={[
        { label: "Tracked items", value: "58" },
        { label: "Low stock", value: "4" },
        { label: "Reserved assets", value: "502" },
      ]}
      columns={["Item", "Available", "Reserved", "Status"]}
      rows={inventory.map((item) => ({
        Item: item.item,
        Available: item.available,
        Reserved: item.reserved,
        Status: item.status,
      }))}
    />
  );
}
