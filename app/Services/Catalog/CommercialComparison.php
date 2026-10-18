<?php

namespace App\Services\Catalog;

class CommercialComparison
{
    public function between(?array $before, array $after): array
    {
        $old = $before ? $this->facts($before) : [];
        $new = $this->facts($after);
        $changes = [];
        foreach (array_unique([...array_keys($old), ...array_keys($new)]) as $label) {
            if (($old[$label] ?? null) !== ($new[$label] ?? null)) {
                $changes[] = ['label' => $label, 'before' => $old[$label] ?? 'Not configured', 'after' => $new[$label] ?? 'Removed'];
            }
        }

        return $changes;
    }

    private function money(string|int $amount): string
    {
        $minor = (int) $amount;

        return 'PHP '.intdiv($minor, 100).'.'.str_pad((string) ($minor % 100), 2, '0', STR_PAD_LEFT);
    }

    private function facts(array $snapshot): array
    {
        $package = isset($snapshot['version']);
        $terms = $package ? $snapshot['version'] : $snapshot;
        $facts = [
            'Name' => $package ? $snapshot['product']['name'] : $snapshot['name'],
            'Description' => ($package ? $snapshot['product']['description'] : ($snapshot['description'] ?? null)) ?: 'None',
            'Base / starting price' => $this->money($terms[$package ? 'base_price_minor' : 'price_minor']),
            'Purchase unit' => str_replace('_', ' ', $terms[$package ? 'price_basis' : 'selling_unit']),
            'Minimum purchase' => (string) $terms['minimum_quantity'],
            'Purchase increment' => (string) $terms['quantity_step'],
            'Maximum purchase' => (string) ($terms['maximum_quantity'] ?? 'No maximum'),
        ];
        if (! $package) {
            foreach ($snapshot['selling_options'] ?? [] as $option) {
                $facts['Selling option: '.$option['label']] = $this->money($option['price_minor']);
            }

            return $facts;
        }
        foreach (['selection_policy' => 'Selection arrangement', 'total_selection_count' => 'Total food selections', 'estimated_capacity' => 'Estimated capacity', 'subgroup_minimum' => 'Group minimum', 'subgroup_step' => 'Group increment', 'subgroup_maximum' => 'Group maximum'] as $key => $label) {
            $facts[$label] = (string) ($terms[$key] ?? 'Not configured');
        }
        $menu = $snapshot['menu'] ?? [];
        $presentation = $snapshot['presentation'] ?? null;
        if ($presentation !== null) {
            $facts['Package Format'] = $presentation['format'] === 'combined_contents' ? 'Combined Contents' : 'Separate Food Servings';
            $describe = fn (array $value): string => $value['quantity'].' '.$value['measurement_unit']['name'];
            $facts['Package presentation'] = $presentation['default'] ? $describe($presentation['default']) : 'Not configured';
            foreach ($presentation['food_overrides'] as $id => $value) {
                $name = collect($menu['food_items'] ?? [])->firstWhere('id', $id)['name'] ?? 'Food #'.$id;
                $facts['Presentation: '.$name] = $describe($value);
            }
        }
        $default = $menu['serving_default'] ?? null;
        $facts['Default serving'] = $default ? implode(' ', array_filter([$default['quantity'], $default['size_label'] ?? null, $default['measurement_unit']['name']])) : 'Not configured';
        $required = array_column($menu['required_categories'] ?? [], 'name');
        sort($required);
        $facts['Required categories'] = implode(', ', $required) ?: 'None';
        foreach ($menu['food_items'] ?? [] as $food) {
            $serving = ! empty($food['included_quantity']) ? $food['included_quantity'].' '.($food['included_unit'] ?? '') : 'Package default';
            $sizes = $menu['food_sizes'][$food['id']] ?? null;
            if ($sizes) {
                $serving = implode('; ', array_map(fn ($size) => implode(' ', array_filter([$size['quantity'], $size['size_label'] ?? null, $sizes['measurement_unit']['name']])).($size['is_default'] ? ' (included)' : ' (+'.$this->money($size['extra_price_minor']).')'), $sizes['choices']));
            }
            $facts['Food: '.$food['name'].(! empty($food['category_name']) ? ' ('.$food['category_name'].')' : '')] = ($food['is_fixed'] ? 'Included' : 'Customer choice').'; '.$serving.'; premium '.$this->money($food['price_adjustment_minor'] ?? 0);
        }
        foreach ($snapshot['groups'] ?? [] as $group) {
            $facts['Choices: '.$group['name']] = ($group['mode'] === 'fixed' ? 'Included Items' : 'Customer Choices').'; choose '.$group['minimum_count'].'–'.$group['maximum_count'].'; included amount '.$group['inclusion_count'].'; '.(($group['unique_dishes'] ?? true) ? 'distinct foods' : 'repeated foods allowed').'; categories '.($group['category_mode'] ?? 'free');
            foreach ($group['options'] as $option) {
                $serving = $option['serving_option'];
                $facts[$group['name'].': '.$option['food_item']['name'].' / '.$serving['label']] = $serving['quantity'].' '.$serving['unit'].'; extra '.$this->money($option['price_adjustment_minor']).($option['is_default'] ? '; default' : '').'; estimated capacity '.($serving['serving_pax'] ?? 'not specified').'; serving description '.($serving['serving_claim'] ?? 'none');
            }
            foreach ($group['category_rules'] as $rule) {
                $facts[$group['name'].' category: '.$rule['category_name']] = $rule['minimum_count'].'–'.$rule['maximum_count'];
            }
            foreach ($group['food_item_rules'] as $rule) {
                $facts[$group['name'].' restriction: '.$rule['food_item_name']] = $rule['effect'];
            }
        }

        return $facts;
    }
}
