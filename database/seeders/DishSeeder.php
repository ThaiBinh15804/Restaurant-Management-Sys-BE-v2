<?php

namespace Database\Seeders;

use App\Models\Dish;
use Illuminate\Database\Seeder;

class DishSeeder extends Seeder
{
    public function run(): void
    {
        $dishes = [
            [
                'id' => 'DISH1',
                'name' => 'Noodles',
                'price' => 49.00,
                'desc' => 'Fresh toasted sourdough bread with olive oil and pomegranate.',
                'category_id' => 'CAT1',
                'cooking_time' => 15,
                'image' => '/images/schezwan_noodles.jpg',
                'is_active' => true,
            ],
            [
                'id' => 'DISH2',
                'name' => 'Spicy Club',
                'price' => 42.00,
                'desc' => 'Pork, chicken and vegetable fried rolls served with lettuce wraps.',
                'category_id' => 'CAT1',
                'cooking_time' => 20,
                'image' => '/images/spicy_club.jpg',
                'is_active' => true,
            ],
            [
                'id' => 'DISH3',
                'name' => 'Baked Brie',
                'price' => 38.00,
                'desc' => 'Pork, chicken and vegetable fried rolls served with lettuce wraps.',
                'category_id' => 'CAT2',
                'cooking_time' => 25,
                'image' => '/images/almond_baked_brie.jpg',
                'is_active' => true,
            ],
            [
                'id' => 'DISH4',
                'name' => 'Flatbread',
                'price' => 49.00,
                'desc' => 'Pork, chicken and vegetable fried rolls served with lettuce wraps.',
                'category_id' => 'CAT3',
                'cooking_time' => 18,
                'image' => '/images/tuscan_flatbread.jpg',
                'is_active' => true,
            ],
        ];

        foreach ($dishes as $dish) {
            Dish::create($dish);
        }
    }
}