import AdminLayout from "../../../layouts/admin-layout";
import { Link } from "@inertiajs/react";
import {
    CompletePackageForm,
    type PackageEditorProps,
} from "../../../features/packages/components/complete-package-form";

export default function PackageEditor(props: PackageEditorProps) {
    const main = props.family.behavior_type === "main_package";
    const back = props.product
        ? main
            ? `/admin/catering-packages/${props.product.id}`
            : `/admin/packages/products/${props.product.id}`
        : main
          ? "/admin/catering-packages"
          : `/admin/add-on-services/${props.family.id}`;
    return (
        <AdminLayout
            title={
                props.product ? `Edit ${props.product.name}` : "Create Package"
            }
            description={props.family.name}
            section={main ? "catering" : "services"}
        >
            <nav
                aria-label="Breadcrumb"
                className="mb-5 text-sm text-slate-600"
            >
                <Link className="text-emerald-800 underline" href={back}>
                    Back to{" "}
                    {props.product
                        ? "Package"
                        : main
                          ? "Catering Packages"
                          : props.family.name}
                </Link>
                <span>
                    {" "}
                    / {props.product ? "Edit Draft" : "Create Package"}
                </span>
            </nav>
            <CompletePackageForm {...props} />
        </AdminLayout>
    );
}
