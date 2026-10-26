<?php

namespace Database\Seeders;

use App\Models\Dish;
use App\Models\DishCategory;
use Illuminate\Database\Seeder;

/** Explicit development fixtures; non-local DatabaseSeeder runs never call this. */
class DevelopmentFoodCatalogSeeder extends Seeder
{
    /** @var array<string, list<string>> */
    private const CATALOG = [
        'Chicken' => ['Fried Chicken', 'Garlic Chicken', 'Chicken Adobo', 'Chicken Afritada', 'Chicken Curry'],
        'Beef' => ['Beef Steak', 'Beef Caldereta', 'Beef Broccoli', 'Beef Mechado', 'Beef Kare-Kare', 'Beef with Mushroom Sauce'],
        'Pork' => ['Pork Steak', 'Pork Adobo', 'Pork Menudo', 'Sweet and Sour Pork', 'Pork BBQ', 'Lumpia Shanghai'],
        'Desserts' => ['Mango Float', 'Fresh Fruits', 'Leche Flan', 'Buko Pandan', 'Fruit Salad', 'Maja Blanca'],
        'Drinks' => ['Distilled Water', 'Juice', 'Iced Tea', 'Soft Drinks', 'Lemonade', 'Four Seasons Juice'],
        'Rice' => ['Fried Rice', 'Plain Rice', 'Steamed Rice'],
        'Lechon' => ['Belly Lechon'],
        'Vegetables' => ['Chopsuey'],
        'Pasta & Noodles' => ['Sotanghon Guisado', 'Carbonara'],
        'Finger Foods & Snacks' => ['Mini Burger', 'Mini Sandwich', 'Mini Hotdog', 'Cheese Sticks', 'Lumpia'],
        'Bread & Sides' => ['Garlic Bread'],
    ];

    public function run(): void
    {
        foreach (self::CATALOG as $categoryName => $foods) {
            $categoryCode = $this->code('category', $categoryName);
            $category = DishCategory::firstOrCreate(
                ['code' => $categoryCode],
                ['name' => $categoryName, 'active' => true, 'sort_order' => 0],
            );
            if ($category->name !== $categoryName) {
                throw new \RuntimeException("Demo category code {$categoryCode} belongs to a different record.");
            }
            foreach ($foods as $foodName) {
                $foodCode = $this->code('food', $foodName);
                $food = Dish::firstOrCreate(
                    ['code' => $foodCode],
                    ['name' => $foodName, 'category_id' => $category->id, 'active' => true],
                );
                if ($food->name !== $foodName || $food->category_id !== $category->id) {
                    throw new \RuntimeException("Demo food code {$foodCode} belongs to a different record.");
                }
            }
        }
    }

    private function code(string $prefix, string $name): string
    {
        return $prefix.'_demo_'.str_replace([' ', '&', '-'], ['_', 'and', '_'], strtolower($name));
    }
}
