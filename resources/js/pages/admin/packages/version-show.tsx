import { Link } from "@inertiajs/react";
import AdminLayout from "../../../layouts/admin-layout";
import { ConfigurationSummary } from "../../../features/packages/components/configuration-summary";
import type {
    PackageProduct,
    PackageVersion,
} from "../../../features/packages/types";

export default function VersionShow({
    product,
    version,
    canRevise,
}: {
    product: PackageProduct;
    version: PackageVersion;
    canRevise: boolean;
}) {
    const main = product.family?.behavior_type === "main_package";
    return (
        <AdminLayout
            title={`${product.name} — ${version.status === "published" ? "Published" : "Abandoned"} Version ${version.version_number}`}
            section={main ? "catering" : "services"}
        >
            <Link
                href={
                    main
                        ? `/admin/catering-packages/${product.id}`
                        : `/admin/packages/products/${product.id}`
                }
                className="mb-5 inline-block text-emerald-800 underline"
            >
                Back to Package
            </Link>
            {version.published_snapshot ? (
                <ConfigurationSummary snapshot={version.published_snapshot} />
            ) : (
                <p>This draft was abandoned. It was never published.</p>
            )}
            {canRevise && (
                <Link
                    method="post"
                    as="button"
                    href={`/admin/packages/products/${product.id}/versions/${version.id}/revise`}
                    className="mt-5 rounded-lg bg-emerald-800 px-4 py-2 text-white"
                >
                    Create Draft from This Version
                </Link>
            )}
        </AdminLayout>
    );
}
