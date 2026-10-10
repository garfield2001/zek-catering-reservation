import { useForm } from "@inertiajs/react";
import { Button } from "../../../components/ui/button";
import { Field } from "../../../components/ui/field";
import { packagePaths } from "../paths";
import type {
    AvailableOption,
    DraftVersion,
    PackageGroup,
    PackageProduct,
    PackageVariant,
} from "../types";
import type { GroupPayload } from "./group-form-types";
import { GroupOptionPicker } from "./group-option-picker";
import { GroupRuleEditors } from "./group-rule-editors";

const kinds: Record<
    string,
    { value: PackageGroup["slot_kind"]; label: string }[]
> = {
    main_package: [
        { value: "item", label: "Included item or choice" },
        { value: "rice", label: "Fixed rice" },
        { value: "drink", label: "Drink choice" },
        { value: "dish", label: "Custom dishes" },
    ],
    package: [
        { value: "item", label: "Included item or choice" },
        { value: "lechon", label: "Lechon kilograms" },
        { value: "tray", label: "Tray choices" },
        { value: "rice", label: "Fixed rice" },
        { value: "drink", label: "Drink choice" },
        { value: "dish", label: "Custom dishes" },
    ],
    packed_meal: [
        { value: "item", label: "Included item or choice" },
        { value: "rice", label: "Fixed rice" },
        { value: "drink", label: "Drink choice" },
        { value: "dish", label: "Meal dishes" },
    ],
    fixed_bundle: [
        { value: "item", label: "Fixed included item" },
        { value: "bundle_item", label: "Legacy fixed bundle item" },
    ],
};

export function GroupForm({
    product,
    variant,
    version,
    group,
    availableOptions,
    categories,
    canPrice,
}: {
    product: PackageProduct;
    variant: PackageVariant;
    version: DraftVersion;
    group: PackageGroup | null;
    availableOptions: AvailableOption[];
    categories: { id: number; name: string }[];
    canPrice: boolean;
}) {
    const behaviorType = product.family?.behavior_type ?? "";
    const choices = kinds[behaviorType] ?? [];
    const form = useForm<GroupPayload>({
        group_code: group?.group_code ?? "",
        name: group?.name ?? "",
        mode: group?.mode ?? "fixed",
        slot_kind: group?.slot_kind ?? choices[0]?.value ?? "dish",
        minimum_count: group?.minimum_count ?? 1,
        maximum_count: group?.maximum_count ?? 1,
        unique_dishes: group?.unique_dishes ?? true,
        category_mode: group?.category_mode ?? "free",
        inclusion_count: group?.inclusion_count ?? "1.000",
        sort_order: group?.sort_order ?? 0,
        options:
            group?.options?.map((option) => ({
                offering_option_id: option.offering_option_id,
                is_default: option.is_default,
                price_adjustment_minor: option.price_adjustment_minor,
            })) ?? [],
        category_rules: group?.category_rules ?? [],
        dish_rules: group?.dish_rules ?? [],
    });
    const visibleOptions = availableOptions.filter((option) =>
        form.data.slot_kind === "lechon"
            ? option.unit === "kg"
            : form.data.slot_kind === "tray"
              ? option.unit === "tray"
              : true,
    );
    return (
        <form
            className="space-y-6"
            onSubmit={(event) => {
                event.preventDefault();
                const path = packagePaths.group(
                    product.id,
                    variant.id,
                    version.id,
                    group?.id,
                );
                if (group) form.put(path);
                else form.post(path);
            }}
        >
            <div className="grid gap-4 rounded-xl border border-stone-200 bg-white p-5 sm:grid-cols-2">
                <Field
                    id="group_code"
                    label="Stable slot code"
                    error={form.errors.group_code}
                >
                    <input
                        id="group_code"
                        required
                        readOnly={!!group}
                        value={form.data.group_code}
                        onChange={(event) =>
                            form.setData("group_code", event.target.value)
                        }
                        placeholder="custom_dishes"
                    />
                </Field>
                <Field
                    id="group_name"
                    label="Slot name"
                    error={form.errors.name}
                >
                    <input
                        id="group_name"
                        required
                        value={form.data.name}
                        onChange={(event) =>
                            form.setData("name", event.target.value)
                        }
                    />
                </Field>
                <Field
                    id="slot_kind"
                    label="Inclusion type"
                    error={form.errors.slot_kind}
                >
                    <select
                        id="slot_kind"
                        value={form.data.slot_kind}
                        onChange={(event) =>
                            form.setData(
                                "slot_kind",
                                event.target.value as PackageGroup["slot_kind"],
                            )
                        }
                    >
                        {choices.map((kind) => (
                            <option key={kind.value} value={kind.value}>
                                {kind.label}
                            </option>
                        ))}
                    </select>
                </Field>
                <Field
                    id="mode"
                    label="Selection mode"
                    error={form.errors.mode}
                >
                    <select
                        id="mode"
                        value={form.data.mode}
                        onChange={(event) =>
                            form.setData(
                                "mode",
                                event.target.value as GroupPayload["mode"],
                            )
                        }
                    >
                        <option value="fixed">Fixed inclusion</option>
                        {behaviorType !== "fixed_bundle" && (
                            <option value="select_one">Choose one</option>
                        )}
                        {behaviorType !== "fixed_bundle" && (
                            <option value="select_many">Choose several</option>
                        )}
                    </select>
                </Field>
                <Field
                    id="minimum_count"
                    label="Minimum selections"
                    error={form.errors.minimum_count}
                >
                    <input
                        id="minimum_count"
                        type="number"
                        min="0"
                        value={form.data.minimum_count}
                        onChange={(event) =>
                            form.setData(
                                "minimum_count",
                                Number(event.target.value),
                            )
                        }
                    />
                </Field>
                <Field
                    id="maximum_count"
                    label="Maximum selections"
                    error={form.errors.maximum_count}
                >
                    <input
                        id="maximum_count"
                        type="number"
                        min="1"
                        value={form.data.maximum_count}
                        onChange={(event) =>
                            form.setData(
                                "maximum_count",
                                Number(event.target.value),
                            )
                        }
                    />
                </Field>
                <Field
                    id="inclusion_count"
                    label="Included option count per purchased unit"
                    error={form.errors.inclusion_count}
                >
                    <input
                        id="inclusion_count"
                        type="number"
                        min="0.001"
                        step="0.001"
                        value={form.data.inclusion_count}
                        onChange={(event) =>
                            form.setData("inclusion_count", event.target.value)
                        }
                    />
                </Field>
                <Field
                    id="sort_order"
                    label="Display order"
                    error={form.errors.sort_order}
                >
                    <input
                        id="sort_order"
                        type="number"
                        value={form.data.sort_order}
                        onChange={(event) =>
                            form.setData(
                                "sort_order",
                                Number(event.target.value),
                            )
                        }
                    />
                </Field>
                <label className="flex items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        checked={form.data.unique_dishes}
                        onChange={(event) =>
                            form.setData("unique_dishes", event.target.checked)
                        }
                    />
                    Require distinct canonical dishes
                </label>
            </div>
            <GroupOptionPicker
                available={visibleOptions}
                selected={form.data.options}
                group={group}
                mode={form.data.mode}
                behaviorType={behaviorType}
                canPrice={canPrice}
                error={form.errors.options}
                onChange={(options) => form.setData("options", options)}
            />
            <GroupRuleEditors
                categoryMode={form.data.category_mode}
                categoryRules={form.data.category_rules}
                dishRules={form.data.dish_rules}
                categories={categories}
                options={visibleOptions}
                maximumCount={form.data.maximum_count}
                error={form.errors.category_rules}
                onMode={(mode) =>
                    form.setData({
                        ...form.data,
                        category_mode: mode,
                        category_rules:
                            mode === "free" ? [] : form.data.category_rules,
                    })
                }
                onCategories={(rules) => form.setData("category_rules", rules)}
                onDishes={(rules) => form.setData("dish_rules", rules)}
            />
            {Object.keys(form.errors).length > 0 && (
                <p role="alert" className="text-sm text-red-700">
                    Review the slot configuration and permitted options.
                </p>
            )}
            <Button disabled={form.processing || visibleOptions.length === 0}>
                {group ? "Save slot" : "Add slot"}
            </Button>
        </form>
    );
}
