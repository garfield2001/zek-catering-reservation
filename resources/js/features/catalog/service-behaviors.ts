export const serviceBehaviors = [
    {
        value: "package",
        label: "Sold by Package",
        detail: "Sell sets with included foods, serving sizes and customer choices.",
    },
    {
        value: "packed_meal",
        label: "Packed Meals",
        detail: "Price each pack, with the same meal for everyone or split meal groups.",
    },
    {
        value: "fixed_bundle",
        label: "Fixed Bundle",
        detail: "Sell a set of fixed inclusions. No substitutions or food surcharges.",
    },
    {
        value: "direct_sale",
        label: "Sold Individually",
        detail: "Independently priced items with their own Drafts and Published Versions.",
    },
] as const;
