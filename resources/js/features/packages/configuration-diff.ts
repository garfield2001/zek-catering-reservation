import { formatPesos } from "./money";
import type { PublishedPackageSnapshot } from "./types";

export function configurationDiff(
    previous: PublishedPackageSnapshot,
    next: PublishedPackageSnapshot,
): string[] {
    const changes: string[] = [];
    if (previous.product.name !== next.product.name)
        changes.push(
            `Package Product name: ${previous.product.name} → ${next.product.name}`,
        );
    if (previous.package.name !== next.package.name)
        changes.push(
            `Package name: ${previous.package.name} → ${next.package.name}`,
        );
    if (previous.version.base_price_minor !== next.version.base_price_minor)
        changes.push(
            `Base Price: ${formatPesos(previous.version.base_price_minor)} → ${formatPesos(next.version.base_price_minor)}`,
        );
    for (const key of [
        "minimum_quantity",
        "quantity_step",
        "maximum_quantity",
        "selection_policy",
    ] as const) {
        if (previous.version[key] !== next.version[key])
            changes.push(
                `${key.replaceAll("_", " ")}: ${previous.version[key] ?? "none"} → ${next.version[key] ?? "none"}`,
            );
    }
    const oldGroups = new Map(
        previous.groups.map((group) => [group.group_code, group]),
    );
    for (const group of next.groups) {
        const old = oldGroups.get(group.group_code);
        if (!old) {
            changes.push(`Added ${group.name}`);
            continue;
        }
        if (
            old.name !== group.name ||
            old.mode !== group.mode ||
            old.minimum_count !== group.minimum_count ||
            old.maximum_count !== group.maximum_count ||
            old.inclusion_count !== group.inclusion_count
        )
            changes.push(`Changed selection rules for ${group.name}`);
        if (
            JSON.stringify(old.category_rules) !==
                JSON.stringify(group.category_rules) ||
            JSON.stringify(old.food_item_rules) !==
                JSON.stringify(group.food_item_rules)
        )
            changes.push(
                `Changed Food Category or Food Item rules for ${group.name}`,
            );
        const oldOptions = new Map(
            old.options.map((option) => [option.offering_option_id, option]),
        );
        for (const option of group.options) {
            const prior = oldOptions.get(option.offering_option_id);
            if (!prior)
                changes.push(`Added ${option.food_item.name} to ${group.name}`);
            else if (
                prior.price_adjustment_minor !== option.price_adjustment_minor
            )
                changes.push(
                    `Changed ${option.food_item.name} adjustment in ${group.name}: ${formatPesos(prior.price_adjustment_minor)} → ${formatPesos(option.price_adjustment_minor)}`,
                );
        }
        for (const option of old.options)
            if (
                !group.options.some(
                    (item) =>
                        item.offering_option_id === option.offering_option_id,
                )
            )
                changes.push(
                    `Removed ${option.food_item.name} from ${group.name}`,
                );
    }
    for (const group of previous.groups)
        if (!next.groups.some((item) => item.group_code === group.group_code))
            changes.push(`Removed ${group.name}`);
    return changes.length ? changes : ["No commercial terms changed."];
}
