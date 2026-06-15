import { redirect } from "next/navigation";

export default function AdminMenusRedirect() {
  redirect("/admin/catalog/dishes");
}
