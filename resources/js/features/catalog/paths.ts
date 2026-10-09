export const catalogPaths = {
    categories: "/admin/catalog/categories",
    category: (id: number) => "/admin/catalog/categories/" + id,
    dishes: "/admin/catalog/dishes",
    dish: (id: number) => "/admin/catalog/dishes/" + id,
    offering: (dishId: number, offeringId: number) =>
        "/admin/catalog/dishes/" + dishId + "/offerings/" + offeringId,
    option: (dishId: number, offeringId: number, optionId: number) =>
        "/admin/catalog/dishes/" +
        dishId +
        "/offerings/" +
        offeringId +
        "/options/" +
        optionId,
    families: "/admin/catalog/families",
};
