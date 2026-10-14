import { useState } from "react";
import { Plus, Search, Pencil, FolderOpen, ChevronDown } from "lucide-react";
import AdminLayout from "../../../layouts/admin-layout";
import { Modal } from "../../../components/ui/modal";
import { Button } from "../../../components/ui/button";
import { CategoryForm } from "../../../features/catalog/components/category-form";
import { FoodItemForm } from "../../../features/catalog/components/food-item-form";
import { FoodCard } from "../../../features/catalog/components/food-card";
import type {
    FoodCategory,
    FoodItem,
    Collection,
} from "../../../features/catalog/types";
import { PaginationControls } from "../../../components/ui/pagination";
import type { Pagination } from "../../../types";

type CatalogEditor =
    | { kind: "food"; item: FoodItem | null; categoryId?: number }
    | { kind: "category"; item: FoodCategory | null }
    | null;
export default function FoodCatalog({
    items,
    categories,
    families,
    filters,
    canManage,
    initialEditor,
}: {
    items: Pagination<FoodItem>;
    categories: FoodCategory[];
    families: Collection[];
    filters: { q?: string; category?: string; service?: string };
    canManage: boolean;
    initialEditor: CatalogEditor;
}) {
    const [editor, setEditor] = useState<CatalogEditor>(initialEditor);
    const close = () => setEditor(null);
    const visibleCategories = categories.filter(
        (category) =>
            (!filters.category || category.id === Number(filters.category)) &&
            (!(filters.q || filters.service) ||
                items.data.some((item) => item.category_id === category.id)),
    );
    return (
        <AdminLayout
            title="Food Catalog"
            description="Your shared food library. Organize foods by category and choose the services that can offer them."
            section="catalog"
        >
            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <p className="text-sm text-slate-500">
                    {items.total} foods · {categories.length} categories
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
                                setEditor({ kind: "food", item: null })
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
            <form
                method="get"
                action="/admin/food-catalog"
                className="mb-6 grid items-end gap-3 rounded-xl border bg-white p-4 sm:grid-cols-2 lg:grid-cols-[2fr_1fr_1fr_auto]"
            >
                <label className="text-xs font-medium text-slate-600">
                    Search foods
                    <div className="relative mt-1">
                        <Search
                            className="absolute left-3 top-3 text-slate-400"
                            size={16}
                        />
                        <input
                            name="q"
                            defaultValue={filters.q}
                            placeholder="Find a food…"
                            className="pl-9"
                        />
                    </div>
                </label>
                <label className="text-xs font-medium text-slate-600">
                    Filter category
                    <select
                        className="mt-1"
                        name="category"
                        defaultValue={filters.category ?? ""}
                    >
                        <option value="">All categories</option>
                        {categories.map((c) => (
                            <option key={c.id} value={c.id}>
                                {c.name}
                            </option>
                        ))}
                    </select>
                </label>
                <label className="text-xs font-medium text-slate-600">
                    Filter service
                    <select
                        className="mt-1"
                        name="service"
                        defaultValue={filters.service ?? ""}
                    >
                        <option value="">All services</option>
                        {families.map((f) => (
                            <option key={f.id} value={f.id}>
                                {f.is_addon ? f.name : "Catering Packages"}
                            </option>
                        ))}
                    </select>
                </label>
                <Button variant="secondary">Search</Button>
            </form>
            <div className="space-y-5">
                {visibleCategories.map((category) => {
                    const foods = items.data.filter(
                        (food) => food.category_id === category.id,
                    );
                    return (
                        <section
                            key={category.id}
                            className="rounded-2xl border bg-white p-4 sm:p-5"
                            aria-label={category.name}
                        >
                            <div className="mb-3 flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <h2 className="flex items-center gap-2 font-semibold">
                                        <FolderOpen
                                            size={18}
                                            className="text-emerald-700"
                                        />
                                        {category.name}
                                        <span className="rounded-full bg-slate-100 px-2 text-xs text-slate-600">
                                            {category.dishes_count ?? 0}
                                        </span>
                                    </h2>
                                    {!category.active && (
                                        <p className="mt-1 text-xs text-amber-800">
                                            Inactive category
                                        </p>
                                    )}
                                </div>
                                {canManage && (
                                    <div className="flex gap-2">
                                        <button
                                            className="rounded-lg p-2 text-slate-500 hover:bg-slate-100"
                                            aria-label={`Edit category ${category.name}`}
                                            onClick={() =>
                                                setEditor({
                                                    kind: "category",
                                                    item: category,
                                                })
                                            }
                                        >
                                            <Pencil size={16} />
                                        </button>
                                        <Button
                                            variant="ghost"
                                            aria-label={`Add Food to ${category.name}`}
                                            onClick={() =>
                                                setEditor({
                                                    kind: "food",
                                                    item: null,
                                                    categoryId: category.id,
                                                })
                                            }
                                        >
                                            <Plus size={16} />
                                            Add Food
                                        </Button>
                                    </div>
                                )}
                            </div>
                            <details open className="group">
                                <summary className="mb-3 flex w-fit items-center gap-1 text-xs text-slate-500">
                                    <ChevronDown size={14} />
                                    {foods.length} foods on this page
                                </summary>
                                <div className="grid gap-3 xl:grid-cols-2">
                                    {foods.map((food) => (
                                        <FoodCard
                                            key={food.id}
                                            item={food}
                                            canManage={canManage}
                                            onEdit={() =>
                                                setEditor({
                                                    kind: "food",
                                                    item: food,
                                                })
                                            }
                                        />
                                    ))}
                                </div>
                                {!foods.length && (
                                    <p className="rounded-lg border border-dashed p-5 text-sm text-slate-500">
                                        {category.dishes_count
                                            ? "No foods from this category on this page. Filter by this category to browse it."
                                            : "No foods yet. Add your first food to this category."}
                                    </p>
                                )}
                            </details>
                        </section>
                    );
                })}
                {!visibleCategories.length && (
                    <div className="rounded-2xl border border-dashed bg-white p-10 text-center">
                        <FolderOpen className="mx-auto mb-3 text-slate-400" />
                        <h2 className="font-semibold">No matching foods</h2>
                        <p className="mt-2 text-sm text-slate-500">
                            Adjust the filters, add a category, or create a food
                            with its new category.
                        </p>
                    </div>
                )}
            </div>
            <PaginationControls {...items} />
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
                        families={families}
                        initialCategoryId={editor.categoryId}
                        workspace
                        onSaved={close}
                    />
                ) : null}
            </Modal>
        </AdminLayout>
    );
}
