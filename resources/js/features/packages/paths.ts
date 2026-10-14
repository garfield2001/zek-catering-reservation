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
    version: (product: number, version: number) =>
        `/admin/packages/products/${product}/versions/${version}`,
    review: (product: number, version: number) =>
        `${packagePaths.version(product, version)}/review`,
    mainMenu: (product: number, version: number) =>
        `/admin/packages/products/${product}/versions/${version}/package-menu/edit`,
    group: (product: number, version: number, group?: number) =>
        `/admin/packages/products/${product}/versions/${version}/groups${group ? `/${group}` : ""}`,
};
