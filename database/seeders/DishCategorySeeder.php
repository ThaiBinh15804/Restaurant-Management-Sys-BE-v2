<?php

namespace Database\Seeders;

use App\Models\DishCategory;
use Illuminate\Database\Seeder;

class DishCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['id' => 'CAT1', 'name' => 'Appetizer', 'desc' => 'Starters and appetizers'],
            ['id' => 'CAT2', 'name' => 'Soup', 'desc' => 'Hot and cold soups'],
            ['id' => 'CAT3', 'name' => 'Dessert', 'desc' => 'Sweet dishes'],
            ['id' => 'CAT4', 'name' => 'Drinks', 'desc' => 'Beverages'],
        ];

        foreach ($categories as $category) {
            DishCategory::create($category);
        }
    }
}