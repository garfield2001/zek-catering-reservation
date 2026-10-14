export type QuantitySource = {
    id: number;
    product: { name: string };
    minimum_quantity: number;
    quantity_step: number;
    maximum_quantity: number | null;
};
