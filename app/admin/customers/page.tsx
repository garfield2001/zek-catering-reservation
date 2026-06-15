import { ManagementPage } from "../management-page";
import { customers } from "@/lib/site-data";

export default function AdminCustomersPage() {
  return (
    <ManagementPage
      eyebrow="Customers"
      title="Customer records"
      description="Keep client history, contact details, event count, and estimated lifetime value ready for follow-up."
      metrics={[
        { label: "Customers", value: "184" },
        { label: "Repeat clients", value: "37" },
        { label: "Top segment", value: "Corporate" },
      ]}
      columns={["Name", "Email", "Events", "Value"]}
      rows={customers.map((customer) => ({
        Name: customer.name,
        Email: customer.email,
        Events: customer.events,
        Value: customer.value,
      }))}
    />
  );
}
