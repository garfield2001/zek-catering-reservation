import AdminLayout from "../../../layouts/admin-layout";
import {
    CompletePackageForm,
    type PackageEditorProps,
} from "../../../features/packages/components/complete-package-form";

export default function PackageEditor(props: PackageEditorProps) {
    return (
        <AdminLayout
            title={
                props.product ? `Edit ${props.product.name}` : "Create Package"
            }
            description={props.family.name}
            section={
                props.family.behavior_type === "main_package"
                    ? "catering"
                    : "services"
            }
        >
            <CompletePackageForm {...props} />
        </AdminLayout>
    );
}
