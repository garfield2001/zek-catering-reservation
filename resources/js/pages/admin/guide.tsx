import { Link } from "@inertiajs/react";
import AdminLayout from "../../layouts/admin-layout";
export default function Guide() {
    return (
        <AdminLayout title="Owner Setup Guide">
            <ol className="max-w-3xl list-inside list-decimal space-y-5 rounded-xl border bg-white p-6">
                <li>
                    <Link
                        className="text-emerald-800 underline"
                        href="/admin/food-catalog"
                    >
                        Food Catalog
                    </Link>
                    : add categories and foods. Foods default to Catering
                    Packages; select other services where relevant. Images are
                    optional: upload, preview, replace or remove them in the
                    food form.
                </li>
                <li>
                    <Link
                        className="text-emerald-800 underline"
                        href="/admin/catering-packages"
                    >
                        Catering Packages
                    </Link>
                    : create a named package. Choose exactly how many foods the
                    menu includes, allowed foods and any required categories.
                    One allowed food in a required category is automatically
                    fixed; Always Include can also fix an individual food.
                </li>
                <li>
                    Complete Details, Menu, and Pricing &amp; Quantity, then use
                    Review &amp; Save. Save Package stores all steps together;
                    publication is a separate Review &amp; Publish confirmation.
                </li>
                <li>
                    Use Edit Package to start a revision; older publications are
                    in Publication History. The published menu stays unchanged
                    until the new draft is published.
                </li>
                <li>
                    Optional: create an{" "}
                    <Link
                        className="text-emerald-800 underline"
                        href="/admin/add-on-services"
                    >
                        Add-on Service
                    </Link>
                    , choose its foods with the shared Menu builder and optional
                    measurements, and configure packages for that service. Or
                    choose Sold Individually to price each food or size, save
                    its Draft and publish its reviewed configuration.
                </li>
            </ol>
            <p className="mt-5 text-sm text-slate-600">
                Staff tools depend on assigned permissions. Customer
                reservations, payments and notifications are not available in
                this milestone.
            </p>
        </AdminLayout>
    );
}
