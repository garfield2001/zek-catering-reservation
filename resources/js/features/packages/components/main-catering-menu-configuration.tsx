import { useMemo, useState } from "react";
import { Field } from "../../../components/ui/field";
import { FoodImage } from "../../../components/ui/food-image";
import { SearchableSelect } from "../../../components/ui/searchable-select";
import { menuSummary, type MenuData } from "../menu-state";
import type { MenuCategory, MenuFood } from "./package-menu-form";
import { SelectedCategoryConfiguration } from "./selected-category-configuration";

const pageSize = 8;

/** Main Catering's category-first picker and its separately saved Draft menu. */
export function MainCateringMenuConfiguration({
    data,
    foods,
    categories,
    errors,
    onChange,
    onAddFood,
}: {
    data: MenuData;
    foods: MenuFood[];
    categories: MenuCategory[];
    errors: Record<string, string>;
    onChange: (values: Partial<MenuData>) => void;
    onAddFood: (category?: number) => void;
}) {
    const [categoryId, setCategoryId] = useState<number | null>(null);
    const [search, setSearch] = useState("");
    const [page, setPage] = useState(1);
    const [activeCategoryId, setActiveCategoryId] = useState<number | null>(
        null,
    );
    const [configuringCategoryId, setConfiguringCategoryId] = useState<
        number | null
    >(null);
    const selected = new Set(data.food_item_ids);
    const summary = menuSummary(data, foods);
    const categoryName = (id: number) =>
        categories.find((category) => category.id === id)?.name ??
        "Uncategorized";
    const visible = useMemo(() => {
        if (categoryId === null) return [];
        const term = search.trim().toLocaleLowerCase();
        return foods.filter(
            (food) =>
                food.category_id === categoryId &&
                (!term || food.name.toLocaleLowerCase().includes(term)),
        );
    }, [categoryId, foods, search]);
    const pageCount = Math.max(1, Math.ceil(visible.length / pageSize));
    const effectivePage = Math.min(page, pageCount);
    const foodsOnPage = visible.slice(
        (effectivePage - 1) * pageSize,
        effectivePage * pageSize,
    );
    const allowed = summary.allowed;
    const choiceRules = summary.choiceRules;
    const toggleFood = (id: number, checked: boolean) =>
        onChange({
            food_item_ids: checked
                ? [...new Set([...data.food_item_ids, id])]
                : data.food_item_ids.filter((current) => current !== id),
        });
    const selectedByCategory = categories
        .map((category) => ({
            category,
            foods: allowed.filter((food) => food.category_id === category.id),
        }))
        .filter(({ foods: categoryFoods }) => categoryFoods.length);
    const activeCategory =
        selectedByCategory.find(
            ({ category }) => category.id === activeCategoryId,
        ) ??
        selectedByCategory[0] ??
        null;
    const activeCategoryIndex = activeCategory
        ? selectedByCategory.findIndex(
              ({ category }) => category.id === activeCategory.category.id,
          )
        : -1;
    const configuringCategory =
        selectedByCategory.find(
            ({ category }) => category.id === configuringCategoryId,
        ) ?? null;

    return (
        <div className="space-y-6">
            <section
                aria-label="Find and select foods"
                className="rounded-2xl border bg-slate-50 p-4 sm:p-6"
            >
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h2 className="text-lg font-semibold">
                            Find & Select Foods
                        </h2>
                        <p className="mt-1 text-sm text-slate-600">
                            Choose a Food Category first. Selecting a Food Item
                            adds only that item to this package.
                        </p>
                    </div>
                    <button
                        type="button"
                        className="text-sm font-medium text-emerald-800 underline"
                        onClick={() => onAddFood(categoryId ?? undefined)}
                    >
                        Add Food Item
                    </button>
                </div>
                <p className="mt-2 text-xs text-slate-500">
                    Authorized users can also create a Food Category from the
                    Food Item form.
                </p>
                <div className="mt-5 grid gap-4 sm:grid-cols-2">
                    <label className="text-sm font-medium">
                        Category
                        <SearchableSelect
                            value={categoryId ?? ""}
                            placeholder="Choose Category"
                            onChange={(value) => {
                                setCategoryId(value ? Number(value) : null);
                                setSearch("");
                                setPage(1);
                            }}
                            options={categories.map((category) => ({
                                value: category.id,
                                label: `${category.name} (${foods.filter((food) => food.category_id === category.id).length})`,
                            }))}
                        />
                    </label>
                    <label className="text-sm font-medium">
                        Search this category
                        <input
                            className="mt-1"
                            type="search"
                            disabled={categoryId === null}
                            value={search}
                            onChange={(event) => {
                                setSearch(event.target.value);
                                setPage(1);
                            }}
                            placeholder={
                                categoryId === null
                                    ? "Choose a category first"
                                    : "Type a food name"
                            }
                        />
                    </label>
                </div>
                {categoryId === null ? (
                    <p className="mt-5 rounded-xl border border-dashed bg-white p-4 text-sm text-slate-600">
                        Choose a Food Category to browse eligible Food Items.
                        Your selected foods stay listed below.
                    </p>
                ) : (
                    <>
                        <div className="mt-5 flex flex-wrap items-center justify-between gap-3 text-sm">
                            <p aria-live="polite" className="text-slate-600">
                                {visible.length} eligible food
                                {visible.length === 1 ? "" : "s"} in{" "}
                                {categoryName(categoryId)}. Page {effectivePage}{" "}
                                of {pageCount}.
                            </p>
                            {!!foodsOnPage.length && (
                                <button
                                    type="button"
                                    className="font-medium text-emerald-800 underline"
                                    onClick={() =>
                                        onChange({
                                            food_item_ids: [
                                                ...new Set([
                                                    ...data.food_item_ids,
                                                    ...foodsOnPage.map(
                                                        (food) => food.id,
                                                    ),
                                                ]),
                                            ],
                                        })
                                    }
                                >
                                    Select this page
                                </button>
                            )}
                        </div>
                        <div className="mt-3 grid gap-3 sm:grid-cols-2">
                            {foodsOnPage.map((food) => (
                                <label
                                    key={food.id}
                                    className={`flex cursor-pointer items-center gap-3 rounded-xl border p-3 text-sm ${selected.has(food.id) ? "border-emerald-700 bg-emerald-50" : "border-slate-200 bg-white"}`}
                                >
                                    <input
                                        type="checkbox"
                                        aria-label={food.name}
                                        checked={selected.has(food.id)}
                                        onChange={(event) =>
                                            toggleFood(
                                                food.id,
                                                event.target.checked,
                                            )
                                        }
                                    />
                                    <FoodImage
                                        name={food.name}
                                        src={food.thumbnail_url}
                                        compact
                                    />
                                    <span className="min-w-0 truncate font-medium">
                                        {food.name}
                                    </span>
                                </label>
                            ))}
                        </div>
                        {!foodsOnPage.length && (
                            <p className="mt-3 rounded-xl border border-dashed bg-white p-4 text-sm text-slate-600">
                                No eligible Food Items match this category and
                                search.
                            </p>
                        )}
                        {pageCount > 1 && (
                            <nav
                                aria-label="Food result pages"
                                className="mt-5 flex flex-wrap items-center gap-2"
                            >
                                <button
                                    type="button"
                                    disabled={effectivePage === 1}
                                    onClick={() => setPage(effectivePage - 1)}
                                >
                                    Previous
                                </button>
                                {Array.from(
                                    { length: pageCount },
                                    (_, index) => index + 1,
                                ).map((number) => (
                                    <button
                                        key={number}
                                        type="button"
                                        aria-current={
                                            number === effectivePage
                                                ? "page"
                                                : undefined
                                        }
                                        className={
                                            number === effectivePage
                                                ? "font-semibold text-emerald-800"
                                                : undefined
                                        }
                                        onClick={() => setPage(number)}
                                    >
                                        {number}
                                    </button>
                                ))}
                                <button
                                    type="button"
                                    disabled={effectivePage === pageCount}
                                    onClick={() => setPage(effectivePage + 1)}
                                >
                                    Next
                                </button>
                            </nav>
                        )}
                    </>
                )}
            </section>

            <section
                aria-label="Selected foods"
                className="space-y-5 rounded-2xl border bg-white p-4 sm:p-6"
            >
                <div>
                    <h2 className="text-lg font-semibold">
                        Selected Foods & Configuration
                    </h2>
                    <p className="mt-1 text-sm text-slate-600">
                        {allowed.length} Food Item
                        {allowed.length === 1 ? "" : "s"} selected. Set the
                        complete menu count, inclusions, and category choices
                        here.
                    </p>
                </div>
                <Field
                    id="selection-total"
                    label="Total included food selections"
                    error={errors.total_selection_count}
                >
                    <input
                        id="selection-total"
                        className={
                            errors.total_selection_count
                                ? "border-red-500"
                                : undefined
                        }
                        required
                        type="number"
                        min="1"
                        value={data.total_selection_count}
                        onChange={(event) =>
                            onChange({
                                total_selection_count: Number(
                                    event.target.value,
                                ),
                            })
                        }
                    />
                </Field>
                {errors.food_item_ids || errors.food_items ? (
                    <p role="alert" className="text-sm text-red-700">
                        {errors.food_item_ids ?? errors.food_items}
                    </p>
                ) : null}
                {!allowed.length ? (
                    <p className="rounded-xl border border-dashed p-4 text-sm text-slate-600">
                        Select Food Items above to configure this package.
                    </p>
                ) : (
                    <>
                        <div
                            className="flex flex-wrap gap-2"
                            aria-label="Selected food categories"
                        >
                            {selectedByCategory.map(
                                ({ category, foods: categoryFoods }) => {
                                    const active =
                                        activeCategory?.category.id ===
                                        category.id;
                                    return (
                                        <button
                                            key={category.id}
                                            type="button"
                                            aria-pressed={active}
                                            className={`rounded-full border px-3 py-1.5 text-sm font-medium ${active ? "border-emerald-700 bg-emerald-50 text-emerald-950" : "bg-white text-slate-700 hover:bg-slate-50"}`}
                                            onClick={() => {
                                                setActiveCategoryId(
                                                    category.id,
                                                );
                                                setConfiguringCategoryId(
                                                    category.id,
                                                );
                                            }}
                                        >
                                            {category.name} (
                                            {categoryFoods.length})
                                        </button>
                                    );
                                },
                            )}
                        </div>
                        {activeCategory && (
                            <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <p className="text-sm font-medium text-slate-500">
                                            Category {activeCategoryIndex + 1}{" "}
                                            of {selectedByCategory.length}
                                        </p>
                                        <h3 className="text-lg font-semibold">
                                            {activeCategory.category.name}
                                        </h3>
                                        <p className="mt-1 text-sm text-slate-600">
                                            {activeCategory.foods
                                                .map((food) => food.name)
                                                .join(", ")}
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        className="text-sm font-medium text-emerald-800 underline"
                                        onClick={() =>
                                            setConfiguringCategoryId(
                                                activeCategory.category.id,
                                            )
                                        }
                                    >
                                        Configure {activeCategory.category.name}
                                    </button>
                                </div>
                                <div className="mt-4 flex items-center justify-between gap-3 text-sm">
                                    <button
                                        type="button"
                                        disabled={activeCategoryIndex <= 0}
                                        onClick={() =>
                                            setActiveCategoryId(
                                                selectedByCategory[
                                                    activeCategoryIndex - 1
                                                ].category.id,
                                            )
                                        }
                                    >
                                        Previous category
                                    </button>
                                    <span aria-live="polite">
                                        {activeCategory.category.name}
                                    </span>
                                    <button
                                        type="button"
                                        disabled={
                                            activeCategoryIndex >=
                                            selectedByCategory.length - 1
                                        }
                                        onClick={() =>
                                            setActiveCategoryId(
                                                selectedByCategory[
                                                    activeCategoryIndex + 1
                                                ].category.id,
                                            )
                                        }
                                    >
                                        Next category
                                    </button>
                                </div>
                            </div>
                        )}
                    </>
                )}
                <aside
                    aria-label="Menu summary"
                    className="rounded-xl bg-emerald-50 p-4 text-sm"
                >
                    <p>
                        <strong>Always Included:</strong>{" "}
                        {summary.effectiveFixed
                            .map((food) => food.name)
                            .join(", ") || "None"}
                        .
                    </p>
                    <p className="mt-1">
                        <strong>Customer Choices:</strong>{" "}
                        {choiceRules
                            .map(
                                (rule) =>
                                    `${categoryName(rule.category_id)}: ${rule.minimum_count === rule.maximum_count ? `exactly ${rule.minimum_count}` : `${rule.minimum_count} to ${rule.maximum_count}`}`,
                            )
                            .join(" · ") || "None"}
                        .
                    </p>
                    <p className="mt-1">
                        Exactly {summary.total} distinct foods, including
                        included foods.
                    </p>
                    {summary.impossible && (
                        <p role="status" className="mt-2 text-amber-900">
                            Resolve the menu configuration before continuing:
                            selected foods, customer choices, and the total must
                            be compatible.
                        </p>
                    )}
                </aside>
            </section>
            <SelectedCategoryConfiguration
                open={configuringCategory !== null}
                onClose={() => setConfiguringCategoryId(null)}
                selectedCategory={configuringCategory}
                data={data}
                errors={errors}
                onChange={onChange}
            />
        </div>
    );
}
