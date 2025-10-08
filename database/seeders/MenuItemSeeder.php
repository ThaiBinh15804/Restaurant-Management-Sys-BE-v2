<?php

namespace Database\Seeders;

use App\Models\MenuItem;
use Illuminate\Database\Seeder;

class MenuItemSeeder extends Seeder
{
    public function run(): void
    {
        $menuItems = [
            [
                'id' => 'MI1',
                'menu_id' => 'MENU1',
                'dish_id' => 'DISH1',
                'price' => 49.00,
                'notes' => 'Available in main menu',
            ],
            [
                'id' => 'MI2',
                'menu_id' => 'MENU1',
                'dish_id' => 'DISH2',
                'price' => 42.00,
                'notes' => 'Available in main menu',
            ],
            [
                'id' => 'MI3',
                'menu_id' => 'MENU1',
                'dish_id' => 'DISH3',
                'price' => 38.00,
                'notes' => 'Available in main menu',
            ],
            [
                'id' => 'MI4',
                'menu_id' => 'MENU1',
                'dish_id' => 'DISH4',
                'price' => 49.00,
                'notes' => 'Available in main menu',
            ],
        ];

        foreach ($menuItems as $menuItem) {
            MenuItem::create($menuItem);
        }
    }
}