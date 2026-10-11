export const packagePaths = {
    index: "/admin/packages/products",
    indexFor: (
        params: {
            familyId?: number | null;
            scope?: "primary" | "addon" | null;
        } = {},
    ) => {
        const query = new URLSearchParams();
        if (params.familyId) query.set("family_id", String(params.familyId));
        if (params.scope) query.set("scope", params.scope);
        return query.toString()
            ? `${packagePaths.index}?${query}`
            : packagePaths.index;
    },
    product: (product: number, familyId?: number | null) =>
        `/admin/packages/products/${product}${familyId ? `?family_id=${familyId}` : ""}`,
    variantCreate: (product: number) =>
        `/admin/packages/products/${product}/variants/create`,
    variants: (product: number) =>
        `/admin/packages/products/${product}/variants`,
    version: (product: number, variant: number, version: number) =>
        `/admin/packages/products/${product}/variants/${variant}/versions/${version}`,
    review: (product: number, variant: number, version: number) =>
        `${packagePaths.version(product, variant, version)}/review`,
    group: (
        product: number,
        variant: number,
        version: number,
        group?: number,
    ) =>
        `/admin/packages/products/${product}/variants/${variant}/versions/${version}/groups${group ? `/${group}` : ""}`,
};
