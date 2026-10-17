import { formatPesos } from "../money";
import type { PublishedPackageSnapshot } from "../types";

export function ConfigurationSummary({
    snapshot,
}: {
    snapshot: PublishedPackageSnapshot;
}) {
    const rules = snapshot.version;
    return (
        <div className="space-y-5 text-sm">
            <section className="rounded-xl border border-stone-200 bg-white p-5">
                <h2 className="font-semibold">Commercial terms</h2>
                <p>
                    {snapshot.product.name} · {snapshot.collection.name}
                </p>
                {snapshot.product.description && (
                    <p>{snapshot.product.description}</p>
                )}
                <p>
                    Base Price: {formatPesos(rules.base_price_minor)}{" "}
                    {rules.price_basis.replaceAll("_", " ")}
                </p>
                <p>
                    Quantity: minimum {rules.minimum_quantity}, increments of{" "}
                    {rules.quantity_step},{" "}
                    {rules.maximum_quantity === null
                        ? "no package-specific maximum"
                        : `maximum ${rules.maximum_quantity}`}
                </p>
                {rules.estimated_capacity && (
                    <p>
                        Estimated capacity: {rules.estimated_capacity} people
                        per purchased unit.
                    </p>
                )}
                <p>
                    Selections:{" "}
                    {rules.selection_policy === "split"
                        ? "Different meal groups permitted"
                        : "Same menu for everyone"}
                </p>
            </section>
            {snapshot.menu && (
                <section className="rounded-xl border bg-white p-5">
                    <h3 className="font-semibold">
                        Menu: exactly {snapshot.menu.total_selection_count}{" "}
                        distinct foods
                    </h3>
                    {snapshot.menu.serving_default && (
                        <p className="mt-2">
                            Default per food without a custom serving:{" "}
                            {snapshot.menu.serving_default.quantity}{" "}
                            {snapshot.menu.serving_default.size_label}{" "}
                            {
                                snapshot.menu.serving_default.measurement_unit
                                    .abbreviation
                            }
                            .
                        </p>
                    )}
                    <p className="mt-2">
                        Required categories:{" "}
                        {snapshot.menu.required_categories
                            .map((c) => c.name)
                            .join(", ") || "None"}
                    </p>
                    {[
                        ...new Set(
                            snapshot.menu.food_items.map(
                                (food) => food.category_name ?? "Foods",
                            ),
                        ),
                    ].map((category) => (
                        <section key={category} className="mt-4">
                            <h4 className="font-medium">{category}</h4>
                            <ul className="mt-2 list-inside list-disc">
                                {snapshot
                                    .menu!.food_items.filter(
                                        (food) =>
                                            (food.category_name ?? "Foods") ===
                                            category,
                                    )
                                    .map((food) => (
                                        <li key={food.id}>
                                            {food.name}
                                            {food.category_name &&
                                                ` · ${food.category_name}`}
                                            {food.always_include &&
                                                " · Always Include"}
                                            {food.is_fixed
                                                ? " — Fixed inclusion"
                                                : " — Customer choice"}
                                            {snapshot.menu?.food_sizes?.[
                                                food.id
                                            ] && (
                                                <ul className="ml-5 list-disc text-slate-600">
                                                    {snapshot.menu.food_sizes[
                                                        food.id
                                                    ].choices.map((choice) => (
                                                        <li key={choice.id}>
                                                            {choice.quantity}{" "}
                                                            {choice.size_label}{" "}
                                                            {
                                                                snapshot.menu!
                                                                    .food_sizes![
                                                                    food.id
                                                                ]
                                                                    .measurement_unit
                                                                    .abbreviation
                                                            }{" "}
                                                            —{" "}
                                                            {choice.is_default
                                                                ? "Included default"
                                                                : `+${formatPesos(choice.extra_price_minor)} per purchased package`}
                                                        </li>
                                                    ))}
                                                </ul>
                                            )}
                                            {food.included_quantity &&
                                                ` · ${food.included_quantity} ${food.included_unit} per purchased unit`}
                                            {Number(
                                                food.price_adjustment_minor ??
                                                    0,
                                            ) > 0 &&
                                                ` · +${formatPesos(food.price_adjustment_minor!)} per purchased unit`}
                                        </li>
                                    ))}
                            </ul>
                        </section>
                    ))}
                </section>
            )}
            {snapshot.groups.map((group) => (
                <section
                    key={group.group_code}
                    className="rounded-xl border border-stone-200 bg-white p-5"
                >
                    <h3 className="font-semibold">
                        {group.name} ·{" "}
                        {group.mode === "fixed"
                            ? "Included Items"
                            : "Customer Choices"}
                    </h3>
                    <p>
                        Choose {group.minimum_count}–{group.maximum_count};{" "}
                        {group.inclusion_count} included per purchased unit.
                    </p>
                    <ul className="mt-2 list-inside list-disc">
                        {group.options.map((option, index) => (
                            <li key={index}>
                                {option.food_item.name} ·{" "}
                                {option.serving_option.quantity}{" "}
                                {option.serving_option.unit} ·{" "}
                                {option.serving_option.label}
                                {` · ${option.food_category.name}`}
                                {option.is_default ? " · default" : ""}
                                {option.serving_option.serving_pax
                                    ? ` · approximately ${option.serving_option.serving_pax} guests`
                                    : ""}
                                {option.serving_option.serving_claim
                                    ? ` · ${option.serving_option.serving_claim}`
                                    : ""}
                                {option.price_adjustment_minor !== "0" &&
                                    ` · ${formatPesos(option.price_adjustment_minor)} adjustment`}
                            </li>
                        ))}
                    </ul>
                    {group.category_rules.map((rule) => (
                        <p key={rule.category_name}>
                            Food Category Requirement: {rule.category_name},{" "}
                            {rule.minimum_count}–{rule.maximum_count}
                        </p>
                    ))}
                    {group.food_item_rules.map((rule) => (
                        <p key={`${rule.food_item_name}-${rule.effect}`}>
                            Food Item Rule: {rule.effect} {rule.food_item_name}
                        </p>
                    ))}
                </section>
            ))}
        </div>
    );
}
