import { Field } from "../../../components/ui/field";
import { FoodImage } from "../../../components/ui/food-image";
import { Modal } from "../../../components/ui/modal";
import { emptyFoodDetail, type MenuData } from "../menu-state";
import type { MenuCategory, MenuFood } from "./package-menu-form";

type SelectedCategory = {
    category: MenuCategory;
    foods: MenuFood[];
};

/** Edits one selected category without changing the parent Draft's save boundary. */
export function SelectedCategoryConfiguration({
    open,
    onClose,
    selectedCategory,
    data,
    errors,
    onChange,
}: {
    open: boolean;
    onClose: () => void;
    selectedCategory: SelectedCategory | null;
    data: MenuData;
    errors: Record<string, string>;
    onChange: (values: Partial<MenuData>) => void;
}) {
    if (!selectedCategory) return null;

    const { category, foods } = selectedCategory;
    const rule = data.category_choice_rules.find(
        (current) => current.category_id === category.id,
    );
    const detail = (id: number) => data.food_details[id] ?? emptyFoodDetail();
    const selectable = foods.filter((food) => !detail(food.id).is_fixed);
    const singleton =
        !!rule && foods.length === 1 && detail(foods[0].id).is_fixed;
    const setRules = (rules: MenuData["category_choice_rules"]) =>
        onChange({
            category_choice_rules: rules,
            required_category_ids: rules.map((current) => current.category_id),
        });
    const setFixed = (id: number, is_fixed: boolean) =>
        onChange({
            food_details: {
                ...data.food_details,
                [id]: { ...detail(id), is_fixed },
            },
        });
    const remove = (id: number) =>
        onChange({
            food_item_ids: data.food_item_ids.filter(
                (current) => current !== id,
            ),
        });

    return (
        <Modal
            open={open}
            onClose={onClose}
            title={`Configure ${category.name} Foods`}
            wide
        >
            <div className="space-y-5">
                <p className="text-sm text-slate-600">
                    Changes remain in this Draft until you use Save & Continue
                    to Review.
                </p>
                <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-slate-50 p-4">
                    <div>
                        <h3 className="font-semibold">Customer Choice</h3>
                        <p className="mt-1 text-sm text-slate-600">
                            Require customers to choose from the selectable{" "}
                            {category.name} foods.
                        </p>
                    </div>
                    <label className="flex items-center gap-2 text-sm font-medium">
                        <input
                            type="checkbox"
                            checked={!!rule}
                            disabled={!selectable.length && !rule}
                            onChange={(event) => {
                                if (event.target.checked)
                                    setRules([
                                        ...data.category_choice_rules,
                                        {
                                            category_id: category.id,
                                            minimum_count: 1,
                                            maximum_count: 1,
                                            maximum_count_explicit: true,
                                        },
                                    ]);
                                else
                                    setRules(
                                        data.category_choice_rules.filter(
                                            (current) =>
                                                current.category_id !==
                                                category.id,
                                        ),
                                    );
                            }}
                        />
                        Require customer choice
                    </label>
                    {rule && !singleton && (
                        <div className="w-full sm:w-48">
                            {rule.minimum_count === rule.maximum_count ? (
                                <Field
                                    id={`choice-count-${category.id}`}
                                    label="Customer must choose"
                                    error={errors.category_choice_rules}
                                >
                                    <input
                                        id={`choice-count-${category.id}`}
                                        className={
                                            errors.category_choice_rules
                                                ? "border-red-500"
                                                : undefined
                                        }
                                        type="number"
                                        min="1"
                                        max={selectable.length}
                                        value={rule.minimum_count}
                                        onChange={(event) => {
                                            const count = Number(
                                                event.target.value,
                                            );
                                            setRules(
                                                data.category_choice_rules.map(
                                                    (current) =>
                                                        current.category_id ===
                                                        category.id
                                                            ? {
                                                                  ...current,
                                                                  minimum_count:
                                                                      count,
                                                                  maximum_count:
                                                                      count,
                                                                  maximum_count_explicit: true,
                                                              }
                                                            : current,
                                                ),
                                            );
                                        }}
                                    />
                                </Field>
                            ) : (
                                <div className="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-950">
                                    <p className="font-medium">
                                        Legacy range preserved
                                    </p>
                                    <p className="mt-1">
                                        Customers choose {rule.minimum_count} to{" "}
                                        {rule.maximum_count} foods.
                                    </p>
                                    <label className="mt-3 block font-medium">
                                        Replace with exact customer choice count
                                        <input
                                            className="mt-1"
                                            type="number"
                                            min="1"
                                            max={selectable.length}
                                            defaultValue=""
                                            onChange={(event) => {
                                                if (!event.target.value) return;
                                                const count = Number(
                                                    event.target.value,
                                                );
                                                setRules(
                                                    data.category_choice_rules.map(
                                                        (current) =>
                                                            current.category_id ===
                                                            category.id
                                                                ? {
                                                                      ...current,
                                                                      minimum_count:
                                                                          count,
                                                                      maximum_count:
                                                                          count,
                                                                      maximum_count_explicit: true,
                                                                  }
                                                                : current,
                                                    ),
                                                );
                                            }}
                                        />
                                    </label>
                                </div>
                            )}
                        </div>
                    )}
                </div>
                {singleton && (
                    <p className="rounded-xl bg-emerald-50 p-3 text-sm text-emerald-950">
                        {foods[0].name} is included automatically because this
                        required category has only one configured Food Item.
                    </p>
                )}
                <div className="subtle-scroll max-h-[45dvh] space-y-3 overflow-y-auto pr-1">
                    {foods.map((food) => (
                        <article
                            key={food.id}
                            className="rounded-xl border border-slate-200 bg-white p-4"
                        >
                            <div className="flex flex-wrap items-center gap-3">
                                <FoodImage
                                    name={food.name}
                                    src={food.thumbnail_url}
                                    compact
                                />
                                <p className="min-w-0 flex-1 font-medium">
                                    {food.name}
                                </p>
                                <button
                                    type="button"
                                    className="text-sm text-rose-700 underline"
                                    onClick={() => remove(food.id)}
                                >
                                    Remove
                                </button>
                            </div>
                            <label className="mt-3 flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    checked={detail(food.id).is_fixed}
                                    onChange={(event) =>
                                        setFixed(food.id, event.target.checked)
                                    }
                                />
                                Always include {food.name}
                            </label>
                        </article>
                    ))}
                </div>
            </div>
        </Modal>
    );
}
