export const packagePaths = {
    index: "/admin/packages/products",
    product: (product: number) => `/admin/packages/products/${product}`,
    variantCreate: (product: number) =>
        `/admin/packages/products/${product}/variants/create`,
    variants: (product: number) =>
        `/admin/packages/products/${product}/variants`,
    version: (product: number, variant: number, version: number) =>
        `/admin/packages/products/${product}/variants/${variant}/versions/${version}`,
    group: (
        product: number,
        variant: number,
        version: number,
        group?: number,
    ) =>
        `/admin/packages/products/${product}/variants/${variant}/versions/${version}/groups${group ? `/${group}` : ""}`,
};
