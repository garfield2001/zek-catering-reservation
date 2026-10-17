import { useEffect, useState } from "react";
import { Link, router } from "@inertiajs/react";
import { toast } from "sonner";
import { Plus, Pencil, Search, X } from "lucide-react";
import AdminLayout from "../../../layouts/admin-layout";
import { Modal } from "../../../components/ui/modal";
import { Button } from "../../../components/ui/button";
import { CategoryForm } from "../../../features/catalog/components/category-form";
import { FoodItemForm } from "../../../features/catalog/components/food-item-form";
import { FoodCard } from "../../../features/catalog/components/food-card";
import type { FoodUsageRow } from "../../../features/catalog/components/food-usage";
import { useLiveFilters } from "../../../features/catalog/use-live-filters";
import type { FoodCategory, FoodItem } from "../../../features/catalog/types";
import { PaginationControls } from "../../../components/ui/pagination";
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
    filters: { q?: string; category?: string; status?: string };
    canManage: boolean;
    initialEditor: CatalogEditor;
    usedIn: Record<number, FoodUsageRow[]>;
}) {
    const [editor, setEditor] = useState<CatalogEditor>(initialEditor);
    const [savedFood, setSavedFood] = useState<FoodItem | null>(null);
    const [highlighted, setHighlighted] = useState<number | null>(null);
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
    const { filters, setFilters, pending } = useLiveFilters(
        "/admin/food-catalog",
        {
            q: initial.q ?? "",
            category: String(initial.category ?? ""),
            status: initial.status ?? "",
        },
    );
    return (
        <AdminLayout
            title="Food Catalog"
            description="Your shared food library. Packages and individual products decide how each food is offered."
            section="catalog"
        >
            <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
                <p role="status" className="text-sm text-slate-500">
                    {pending
                        ? "Updating results…"
                        : `${items.total} matching foods`}
                </p>
                {canManage ? (
                    <div className="flex gap-2">
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
                    </div>
                ) : (
                    <p className="text-sm text-slate-500">
                        Viewing access only. Ask an Administrator for catalog
                        editing permission.
                    </p>
                )}
            </div>
            <section
                className="mb-6 grid gap-3 rounded-xl border bg-white p-4 sm:grid-cols-[minmax(0,2fr)_1fr_1fr_auto]"
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
                                {category.name} ({category.dishes_count ?? 0})
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
                <Button
                    className="self-end"
                    variant="ghost"
                    onClick={() =>
                        setFilters({ q: "", category: "", status: "" })
                    }
                >
                    <X size={14} />
                    Clear filters
                </Button>
            </section>
            {items.data.length ? (
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
                    <Link
                        href="/admin/catalog/measurement-units"
                        className="text-sm text-emerald-800 underline"
                    >
                        Measurement Units
                    </Link>
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
                        dish={editor.item}
                        categories={categories}
                        initialCategoryId={editor.categoryId}
                        workspace
                        onSaved={close}
                        onPersisted={foodPersisted}
                    />
                ) : null}
            </Modal>
        </AdminLayout>
    );
}
