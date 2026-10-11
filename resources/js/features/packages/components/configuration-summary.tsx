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
                    {snapshot.product.name} · {snapshot.variant.name} ·{" "}
                    {snapshot.collection.name}
                </p>
                {snapshot.product.description && (
                    <p>{snapshot.product.description}</p>
                )}
                {snapshot.variant.description && (
                    <p>{snapshot.variant.description}</p>
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
                <p>
                    Selections:{" "}
                    {rules.selection_policy === "split"
                        ? "split groups permitted"
                        : "uniform"}
                </p>
            </section>
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
