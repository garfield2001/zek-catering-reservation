import { useEffect, useState } from "react";
import { Link, router } from "@inertiajs/react";
import { toast } from "sonner";
import { Grid2X2, List, Plus, Pencil, Search, X } from "lucide-react";
import AdminLayout from "../../../layouts/admin-layout";
import { Modal } from "../../../components/ui/modal";
import { Button } from "../../../components/ui/button";
import { CategoryForm } from "../../../features/catalog/components/category-form";
import { FoodItemForm } from "../../../features/catalog/components/food-item-form";
import { FoodCard } from "../../../features/catalog/components/food-card";
import { FoodList } from "../../../features/catalog/components/food-list";
import type { FoodUsageRow } from "../../../features/catalog/components/food-usage";
import { useLiveFilters } from "../../../features/catalog/use-live-filters";
import type { FoodCategory, FoodItem } from "../../../features/catalog/types";
import { PaginationControls } from "../../../components/ui/pagination";
import { MeasurementUnitManager } from "../../../features/catalog/components/measurement-unit-manager";
import type { Pagination } from "../../../types";

type CatalogEditor =
    | { kind: "food"; item: FoodItem | null; categoryId?: number }
    | { kind: "category"; item: FoodCategory | null }
    | null;
export default function FoodCatalog({
    items,
    categories,
    filters: initial,
    canManage,
    initialEditor,
    usedIn,
}: {
    items: Pagination<FoodItem>;
    categories: FoodCategory[];
    filters: {
        q?: string;
        category?: string;
        status?: string;
        sort?: string;
        per_page?: string;
    };
    canManage: boolean;
    initialEditor: CatalogEditor;
    usedIn: Record<number, FoodUsageRow[]>;
}) {
    const [editor, setEditor] = useState<CatalogEditor>(initialEditor);
    const [foodFormRevision, setFoodFormRevision] = useState(0);
    const [savedFood, setSavedFood] = useState<FoodItem | null>(null);
    const [highlighted, setHighlighted] = useState<number | null>(null);
    const [managingUnits, setManagingUnits] = useState(false);
    const [view, setView] = useState<"list" | "grid">(() => {
        if (typeof window === "undefined") return "list";
        return window.localStorage.getItem("catalog-food-view") === "grid"
            ? "grid"
            : "list";
    });
    useEffect(() => {
        if (highlighted === null) return;
        const timeout = window.setTimeout(() => setHighlighted(null), 3000);
        return () => window.clearTimeout(timeout);
    }, [highlighted]);
    const foodPersisted = (food: FoodItem) => {
        setSavedFood(food);
        toast.success("Food item saved.");
        // Let the child close/reset its dirty form before refreshing this exact URL.
        setTimeout(
            () =>
                router.reload({
                    only: ["items", "categories", "usedIn"],
                    onSuccess: () => setHighlighted(food.id),
                }),
            0,
        );
    };
    const close = () => setEditor(null);
    const saveAndAddAnother = (food: FoodItem) => {
        foodPersisted(food);
        setEditor({ kind: "food", item: null, categoryId: food.category_id });
        setFoodFormRevision((revision) => revision + 1);
    };
    const { filters, setFilters, pending } = useLiveFilters(
        "/admin/food-catalog",
        {
            q: initial.q ?? "",
            category: String(initial.category ?? ""),
            status: initial.status ?? "",
            sort: initial.sort ?? "name_asc",
            per_page: initial.per_page ?? "24",
        },
    );
    const changeView = (next: "list" | "grid") => {
        setView(next);
        window.localStorage.setItem("catalog-food-view", next);
    };
    return (
        <AdminLayout
            title="Food Catalog"
            description="Your shared food library. Packages and individual products decide how each food is offered."
            section="catalog"
        >
            <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
                <p role="status" className="text-sm text-slate-500">
                    {pending
                        ? "Loading filtered foods…"
                        : `${items.total} matching foods`}
                </p>
                <div className="flex flex-wrap items-center gap-2">
                    <div
                        className="inline-flex rounded-lg border bg-white p-1"
                        role="group"
                        aria-label="Food catalog view"
                    >
                        <Button
                            type="button"
                            variant={view === "list" ? "primary" : "ghost"}
                            aria-pressed={view === "list"}
                            onClick={() => changeView("list")}
                        >
                            <List size={16} />
                            List
                        </Button>
                        <Button
                            type="button"
                            variant={view === "grid" ? "primary" : "ghost"}
                            aria-pressed={view === "grid"}
                            onClick={() => changeView("grid")}
                        >
                            <Grid2X2 size={16} />
                            Grid
                        </Button>
                    </div>
                    {canManage ? (
                        <>
                            <Button
                                variant="secondary"
                                onClick={() =>
                                    setEditor({ kind: "category", item: null })
                                }
                            >
                                <Plus size={16} />
                                Add Category
                            </Button>
                            <Button
                                onClick={() =>
                                    setEditor({
                                        kind: "food",
                                        item: null,
                                        categoryId: filters.category
                                            ? Number(filters.category)
                                            : undefined,
                                    })
                                }
                            >
                                <Plus size={16} />
                                Add Food Item
                            </Button>
                        </>
                    ) : (
                        <p className="text-sm text-slate-500">
                            Viewing access only. Ask an Administrator for
                            catalog editing permission.
                        </p>
                    )}
                </div>
            </div>
            <section
                className="mb-6 grid gap-3 rounded-xl border bg-white p-4 sm:grid-cols-[minmax(0,2fr)_1fr_1fr_1fr_1fr_auto]"
                aria-label="Food filters"
            >
                <label className="text-xs font-medium text-slate-600">
                    Search foods or categories
                    <div className="relative mt-1">
                        <Search
                            size={16}
                            className="absolute left-3 top-3 text-slate-400"
                        />
                        <input
                            type="search"
                            className="pl-9"
                            value={filters.q}
                            onChange={(event) =>
                                setFilters({
                                    ...filters,
                                    q: event.target.value,
                                })
                            }
                            placeholder="Start typing…"
                        />
                    </div>
                </label>
                <label className="text-xs font-medium text-slate-600">
                    Category
                    <select
                        className="mt-1"
                        value={filters.category}
                        onChange={(event) =>
                            setFilters({
                                ...filters,
                                category: event.target.value,
                            })
                        }
                    >
                        <option value="">All Foods</option>
                        {categories.map((category) => (
                            <option key={category.id} value={category.id}>
                                {category.name} ({category.dishes_count ?? 0}{" "}
                                total)
                                {!category.active ? " · Inactive" : ""}
                            </option>
                        ))}
                    </select>
                </label>
                <label className="text-xs font-medium text-slate-600">
                    Availability
                    <select
                        className="mt-1"
                        value={filters.status}
                        onChange={(event) =>
                            setFilters({
                                ...filters,
                                status: event.target.value,
                            })
                        }
                    >
                        <option value="">All statuses</option>
                        <option value="active">Available foods</option>
                        <option value="inactive">
                            Inactive food or category
                        </option>
                    </select>
                </label>
                <label className="text-xs font-medium text-slate-600">
                    Sort
                    <select
                        className="mt-1"
                        value={filters.sort}
                        onChange={(event) =>
                            setFilters({
                                ...filters,
                                sort: event.target.value,
                            })
                        }
                    >
                        <option value="name_asc">Name A–Z</option>
                        <option value="name_desc">Name Z–A</option>
                        <option value="recent">Recently updated</option>
                        <option value="category">Category</option>
                    </select>
                </label>
                <label className="text-xs font-medium text-slate-600">
                    Per page
                    <select
                        className="mt-1"
                        value={filters.per_page}
                        onChange={(event) =>
                            setFilters({
                                ...filters,
                                per_page: event.target.value,
                            })
                        }
                    >
                        <option value="12">12 foods</option>
                        <option value="24">24 foods</option>
                        <option value="48">48 foods</option>
                    </select>
                </label>
                <Button
                    className="self-end"
                    variant="ghost"
                    onClick={() =>
                        setFilters({
                            q: "",
                            category: "",
                            status: "",
                            sort: "name_asc",
                            per_page: filters.per_page,
                        })
                    }
                >
                    <X size={14} />
                    Clear filters
                </Button>
            </section>
            {pending ? (
                <div
                    aria-label={`${view === "list" ? "List" : "Grid"} results loading`}
                    className={
                        view === "list"
                            ? "space-y-2 rounded-2xl border bg-white p-4"
                            : "grid grid-cols-[repeat(auto-fill,minmax(min(100%,220px),280px))] gap-5"
                    }
                >
                    {Array.from({ length: view === "list" ? 6 : 8 }).map(
                        (_, index) => (
                            <div
                                key={index}
                                className={`animate-pulse rounded-xl bg-slate-100 motion-reduce:animate-none ${view === "list" ? "h-14" : "h-44"}`}
                            />
                        ),
                    )}
                </div>
            ) : items.data.length ? (
                view === "list" ? (
                    <FoodList
                        items={items.data}
                        canManage={canManage}
                        highlightedId={highlighted}
                        onEdit={(food) =>
                            setEditor({ kind: "food", item: food })
                        }
                    />
                ) : (
                    <div className="grid grid-cols-[repeat(auto-fill,minmax(min(100%,220px),280px))] gap-5">
                        {items.data.map((food) => (
                            <FoodCard
                                key={food.id}
                                item={food}
                                highlighted={highlighted === food.id}
                                usage={usedIn[food.id] ?? []}
                                canManage={canManage}
                                onEdit={() =>
                                    setEditor({ kind: "food", item: food })
                                }
                            />
                        ))}
                    </div>
                )
            ) : (
                <div className="rounded-xl border border-dashed bg-white p-10 text-center">
                    <h2 className="font-semibold">No matching foods</h2>
                    <p className="mt-2 text-sm text-slate-500">
                        Clear filters, choose another category, or add your
                        first food.
                    </p>
                </div>
            )}
            {savedFood &&
                !items.data.some((food) => food.id === savedFood.id) && (
                    <p
                        role="status"
                        className="my-4 rounded-lg border bg-emerald-50 p-3 text-sm"
                    >
                        {savedFood.name} was saved outside the current filters
                        or page.{" "}
                        <Link
                            href={`/admin/catalog/dishes/${savedFood.id}`}
                            className="underline"
                        >
                            View Newly Added or Updated Food
                        </Link>
                    </p>
                )}
            <PaginationControls {...items} />
            <details className="mt-8 rounded-xl border bg-white p-4">
                <summary className="cursor-pointer font-medium">
                    Catalog Settings
                </summary>
                <div className="mt-4 space-y-4">
                    <Button
                        variant="secondary"
                        onClick={() => setManagingUnits(true)}
                    >
                        Manage Measurement Units
                    </Button>
                    <div className="flex flex-wrap gap-2">
                        {categories.map((category) => (
                            <div
                                key={category.id}
                                className="inline-flex items-center gap-2 rounded-lg border px-3 py-2 text-sm"
                            >
                                <span>
                                    {category.name}
                                    {!category.active && " · Inactive"}
                                </span>
                                {canManage && (
                                    <button
                                        aria-label={`Edit category ${category.name}`}
                                        onClick={() =>
                                            setEditor({
                                                kind: "category",
                                                item: category,
                                            })
                                        }
                                    >
                                        <Pencil size={14} />
                                    </button>
                                )}
                            </div>
                        ))}
                    </div>
                </div>
            </details>
            <Modal
                open={editor !== null}
                onClose={close}
                title={
                    editor?.kind === "category"
                        ? editor.item
                            ? "Edit Category"
                            : "Add Category"
                        : editor?.item
                          ? "Edit Food Item"
                          : "Add Food Item"
                }
            >
                {editor?.kind === "category" ? (
                    <CategoryForm
                        category={editor.item}
                        workspace
                        onSaved={close}
                    />
                ) : editor?.kind === "food" ? (
                    <FoodItemForm
                        key={`${editor.item?.id ?? "new"}-${foodFormRevision}`}
                        dish={editor.item}
                        categories={categories}
                        initialCategoryId={editor.categoryId}
                        workspace
                        onSaved={close}
                        onPersisted={foodPersisted}
                        onSavedAnother={saveAndAddAnother}
                    />
                ) : null}
            </Modal>
            <MeasurementUnitManager
                open={managingUnits}
                onClose={() => setManagingUnits(false)}
            />
        </AdminLayout>
    );
}
