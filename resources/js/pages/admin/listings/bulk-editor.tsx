import { Link, useForm } from "@inertiajs/react";
import { useMemo, useState } from "react";
import AdminLayout from "../../../layouts/admin-layout";
import { Button } from "../../../components/ui/button";
import { FoodImage } from "../../../components/ui/food-image";
import { ConfirmDialog } from "../../../components/ui/confirm-dialog";
import { UnsavedChanges } from "../../../components/ui/unsaved-changes";
import { SearchableSelect } from "../../../components/ui/searchable-select";
import type { Collection } from "../../../features/catalog/types";

type Food = {
    id: number;
    name: string;
    category_id: number;
    thumbnail_url: string | null;
    category: { id: number; name: string };
    existing_listing_id: number | null;
};
type ListingInput = {
    dish_id: number;
    creation_token: string;
    selling_unit: string;
    price_pesos: string;
    minimum_quantity: string;
    quantity_step: string;
    maximum_quantity: string;
};

const emptyListing = (dishId: number): ListingInput => ({
    dish_id: dishId,
    creation_token: crypto.randomUUID(),
    selling_unit: "tray",
    price_pesos: "",
    minimum_quantity: "1",
    quantity_step: "1",
    maximum_quantity: "",
});

export default function BulkListingEditor({
    family,
    foods,
    categories,
    canPrice,
}: {
    family: Collection;
    foods: Food[];
    categories: { id: number; name: string }[];
    canPrice: boolean;
}) {
    const [step, setStep] = useState<"select" | "configure" | "review">(
        "select",
    );
    const [categoryId, setCategoryId] = useState<number | "all">("all");
    const [search, setSearch] = useState("");
    const [shared, setShared] = useState({
        selling_unit: "tray",
        minimum_quantity: "1",
        quantity_step: "1",
        maximum_quantity: "",
        price_pesos: "",
    });
    const [confirmPrice, setConfirmPrice] = useState(false);
    const form = useForm<{ listings: ListingInput[] }>({ listings: [] });
    const selected = new Set(form.data.listings.map((item) => item.dish_id));
    const visible = useMemo(() => {
        const term = search.trim().toLocaleLowerCase();
        return foods.filter(
            (food) =>
                (categoryId === "all" || food.category_id === categoryId) &&
                (!term || food.name.toLocaleLowerCase().includes(term)),
        );
    }, [categoryId, foods, search]);
    const updateSelection = (food: Food, checked: boolean) =>
        form.setData(
            "listings",
            checked
                ? [...form.data.listings, emptyListing(food.id)]
                : form.data.listings.filter((item) => item.dish_id !== food.id),
        );
    const update = (dishId: number, values: Partial<ListingInput>) =>
        form.setData(
            "listings",
            form.data.listings.map((item) =>
                item.dish_id === dishId ? { ...item, ...values } : item,
            ),
        );
    const applyShared = () =>
        form.setData(
            "listings",
            form.data.listings.map((item) => ({
                ...item,
                selling_unit: shared.selling_unit,
                minimum_quantity: shared.minimum_quantity,
                quantity_step: shared.quantity_step,
                maximum_quantity: shared.maximum_quantity,
            })),
        );
    const applyPrice = () => {
        form.setData(
            "listings",
            form.data.listings.map((item) => ({
                ...item,
                price_pesos: shared.price_pesos,
            })),
        );
        setConfirmPrice(false);
    };
    const foodFor = (id: number) => foods.find((food) => food.id === id)!;
    const invalid = form.data.listings.some(
        (item) =>
            !item.price_pesos ||
            !item.selling_unit ||
            !item.minimum_quantity ||
            !item.quantity_step,
    );
    return (
        <AdminLayout
            title="Add Foods to Sell"
            description={`${family.name} — select Food Items, apply shared selling rules deliberately, then set each independent price.`}
            section="services"
        >
            <UnsavedChanges dirty={form.isDirty} />
            <nav
                aria-label="Breadcrumb"
                className="mb-5 text-sm text-slate-600"
            >
                <Link
                    className="text-emerald-800 underline"
                    href={`/admin/add-on-services/${family.id}`}
                >
                    Back to {family.name}
                </Link>
                <span> / Add Foods to Sell</span>
            </nav>
            <div
                className="mb-6 flex flex-wrap gap-2 text-sm"
                aria-label="Bulk listing steps"
            >
                {[
                    ["select", "1. Select foods"],
                    ["configure", "2. Configure"],
                    ["review", "3. Review"],
                ].map(([key, label]) => (
                    <span
                        key={key}
                        className={`rounded-full px-3 py-1 ${step === key ? "bg-emerald-800 text-white" : "bg-slate-100 text-slate-600"}`}
                    >
                        {label}
                    </span>
                ))}
            </div>
            {step === "select" && (
                <section className="space-y-5 rounded-2xl border bg-white p-5">
                    <div className="grid gap-3 sm:grid-cols-2">
                        <label className="text-sm font-medium">
                            Category
                            <SearchableSelect
                                value={categoryId}
                                placeholder="All eligible foods"
                                onChange={(value) =>
                                    setCategoryId(
                                        value === "all" ? "all" : Number(value),
                                    )
                                }
                                options={[
                                    {
                                        value: "all",
                                        label: "All eligible foods",
                                    },
                                    ...categories.map((category) => ({
                                        value: category.id,
                                        label: `${category.name} (${foods.filter((food) => food.category_id === category.id).length})`,
                                    })),
                                ]}
                            />
                        </label>
                        <label className="text-sm font-medium">
                            Search all eligible foods
                            <input
                                className="mt-1"
                                type="search"
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Food name"
                            />
                        </label>
                    </div>
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <p className="text-sm text-slate-600">
                            Selected Foods — {form.data.listings.length} item
                            {form.data.listings.length === 1 ? "" : "s"}
                        </p>
                        <button
                            type="button"
                            className="text-sm text-emerald-800 underline"
                            onClick={() =>
                                form.setData("listings", [
                                    ...form.data.listings,
                                    ...visible
                                        .filter(
                                            (food) =>
                                                !food.existing_listing_id &&
                                                !selected.has(food.id),
                                        )
                                        .map((food) => emptyListing(food.id)),
                                ])
                            }
                        >
                            Select all shown (
                            {
                                visible.filter(
                                    (food) => !food.existing_listing_id,
                                ).length
                            }
                            )
                        </button>
                    </div>
                    <div className="grid max-h-[32rem] gap-2 overflow-y-auto pr-1 sm:grid-cols-2">
                        {visible.map((food) => (
                            <article
                                key={food.id}
                                className="flex items-center gap-3 rounded-xl border p-3"
                            >
                                <FoodImage
                                    name={food.name}
                                    src={food.thumbnail_url}
                                    compact
                                />
                                <div className="min-w-0 flex-1">
                                    <p className="truncate font-medium">
                                        {food.name}
                                    </p>
                                    <p className="text-xs text-slate-500">
                                        {food.category.name}
                                    </p>
                                </div>
                                {food.existing_listing_id ? (
                                    <Link
                                        className="text-sm text-emerald-800 underline"
                                        href={`/admin/items/${food.existing_listing_id}`}
                                    >
                                        Already added
                                    </Link>
                                ) : (
                                    <label className="flex items-center gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            checked={selected.has(food.id)}
                                            onChange={(event) =>
                                                updateSelection(
                                                    food,
                                                    event.target.checked,
                                                )
                                            }
                                        />
                                        Add
                                    </label>
                                )}
                            </article>
                        ))}
                    </div>
                    <div className="flex justify-end">
                        <Button
                            disabled={!form.data.listings.length}
                            onClick={() => setStep("configure")}
                        >
                            Configure Selected Foods
                        </Button>
                    </div>
                </section>
            )}
            {step === "configure" && (
                <section className="space-y-5 rounded-2xl border bg-white p-5">
                    <div className="rounded-xl bg-slate-50 p-4">
                        <h2 className="font-semibold">
                            Shared selling settings
                        </h2>
                        <p className="mt-1 text-sm text-slate-600">
                            Apply a shared unit and purchase rules only when you
                            intend all selected listings to receive them.
                        </p>
                        <div className="mt-3 grid gap-3 sm:grid-cols-4">
                            {(
                                [
                                    ["selling_unit", "Selling unit"],
                                    ["minimum_quantity", "Minimum"],
                                    ["quantity_step", "Increment"],
                                    ["maximum_quantity", "Maximum (optional)"],
                                ] as const
                            ).map(([key, label]) => (
                                <label
                                    key={key}
                                    className="text-sm font-medium"
                                >
                                    {label}
                                    <input
                                        className="mt-1"
                                        value={shared[key]}
                                        type={
                                            key === "selling_unit"
                                                ? "text"
                                                : "number"
                                        }
                                        min={
                                            key === "selling_unit"
                                                ? undefined
                                                : "1"
                                        }
                                        onChange={(event) =>
                                            setShared({
                                                ...shared,
                                                [key]: event.target.value,
                                            })
                                        }
                                    />
                                </label>
                            ))}
                        </div>
                        <Button
                            className="mt-3"
                            variant="secondary"
                            onClick={applyShared}
                        >
                            Apply shared settings to {form.data.listings.length}{" "}
                            items
                        </Button>
                    </div>
                    <div className="space-y-3">
                        <h2 className="font-semibold">Independent prices</h2>
                        {form.data.listings.map((item) => {
                            const food = foodFor(item.dish_id);
                            return (
                                <div
                                    key={item.dish_id}
                                    className="grid gap-3 rounded-xl border p-4 sm:grid-cols-[minmax(0,1fr)_10rem_9rem]"
                                >
                                    <div className="flex items-center gap-3">
                                        <FoodImage
                                            name={food.name}
                                            src={food.thumbnail_url}
                                            compact
                                        />
                                        <div>
                                            <p className="font-medium">
                                                {food.name}
                                            </p>
                                            <p className="text-xs text-slate-500">
                                                {item.minimum_quantity} minimum
                                                · every {item.quantity_step}{" "}
                                                {item.selling_unit}
                                            </p>
                                        </div>
                                    </div>
                                    <label className="text-sm font-medium">
                                        Unit
                                        <input
                                            className="mt-1"
                                            value={item.selling_unit}
                                            onChange={(event) =>
                                                update(item.dish_id, {
                                                    selling_unit:
                                                        event.target.value,
                                                })
                                            }
                                        />
                                    </label>
                                    <label className="text-sm font-medium">
                                        Price (PHP)
                                        <input
                                            className="mt-1"
                                            required
                                            inputMode="decimal"
                                            value={item.price_pesos}
                                            onChange={(event) =>
                                                update(item.dish_id, {
                                                    price_pesos:
                                                        event.target.value,
                                                })
                                            }
                                        />
                                    </label>
                                </div>
                            );
                        })}
                    </div>
                    <div className="flex flex-wrap justify-between gap-3">
                        <Button
                            variant="ghost"
                            onClick={() => setStep("select")}
                        >
                            Back to food selection
                        </Button>
                        <div className="flex gap-2">
                            <input
                                aria-label="Same price for selected foods"
                                className="w-36"
                                inputMode="decimal"
                                value={shared.price_pesos}
                                onChange={(event) =>
                                    setShared({
                                        ...shared,
                                        price_pesos: event.target.value,
                                    })
                                }
                                placeholder="Same price (PHP)"
                            />
                            <Button
                                variant="secondary"
                                disabled={!shared.price_pesos}
                                onClick={() => setConfirmPrice(true)}
                            >
                                Apply same price
                            </Button>
                            <Button onClick={() => setStep("review")}>
                                Review selected foods
                            </Button>
                        </div>
                    </div>
                </section>
            )}
            {step === "review" && (
                <form
                    className="space-y-5 rounded-2xl border bg-white p-5"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(
                            `/admin/add-on-services/${family.id}/items/bulk`,
                        );
                    }}
                >
                    <h2 className="font-semibold">
                        Review independent item drafts
                    </h2>
                    <p className="text-sm text-slate-600">
                        Saving creates {form.data.listings.length} separate
                        Draft listings. Publication remains an individual review
                        step.
                    </p>
                    {form.data.listings.map((item, index) => (
                        <div
                            key={item.dish_id}
                            className="flex flex-wrap items-center justify-between gap-2 rounded-xl border p-3 text-sm"
                        >
                            <span className="font-medium">
                                {foodFor(item.dish_id).name}
                            </span>
                            <span>
                                {item.selling_unit} · PHP{" "}
                                {item.price_pesos || "Price required"} · min.{" "}
                                {item.minimum_quantity}
                            </span>
                            {form.errors[
                                `listings.${index}.dish_id` as keyof typeof form.errors
                            ] && (
                                <span className="text-rose-700">
                                    Already configured
                                </span>
                            )}
                        </div>
                    ))}
                    {invalid && (
                        <p role="alert" className="text-rose-700">
                            Every selected Food Item needs a selling unit,
                            minimum, increment, and independent price before
                            saving.
                        </p>
                    )}
                    <div className="flex justify-between">
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={() => setStep("configure")}
                        >
                            Back
                        </Button>
                        <Button
                            disabled={form.processing || invalid || !canPrice}
                        >
                            Save {form.data.listings.length} Drafts
                        </Button>
                    </div>
                </form>
            )}
            <ConfirmDialog
                open={confirmPrice}
                onClose={() => setConfirmPrice(false)}
                title="Replace all selected prices?"
                description={`This will set PHP ${shared.price_pesos || "0"} on ${form.data.listings.length} unsaved listings. It does not affect existing or published listings.`}
                confirmLabel="Apply same price"
                onConfirm={applyPrice}
            />
        </AdminLayout>
    );
}
