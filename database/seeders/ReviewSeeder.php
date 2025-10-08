<?php

namespace Database\Seeders;

use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReviewSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'customer@restaurant.com')->first();
        
        if (!$user) {
            throw new \Exception('User with email customer@restaurant.com not found');
        }

        $customer = $user->customerProfile;

        $reviews = [
            [
                'id' => 'REV1',
                'target_type' => 1, // Dish
                'target_id' => 'DISH1',
                'rating' => 5,
                'comment' => 'Delicious noodles! Highly recommend.',
                'is_display' => true,
                'is_public' => true,
                'customer_id' => $customer->id,
                'created_at' => now(),
            ],
            [
                'id' => 'REV2',
                'target_type' => 1, // Dish
                'target_id' => 'DISH1',
                'rating' => 4,
                'comment' => 'Great taste, but a bit spicy.',
                'is_display' => true,
                'is_public' => true,
                'customer_id' => $customer->id,
                'created_at' => now(),
            ],
            [
                'id' => 'REV3',
                'target_type' => 1, // Dish
                'target_id' => 'DISH2',
                'rating' => 4,
                'comment' => 'Tasty and well-prepared.',
                'is_display' => true,
                'is_public' => true,
                'customer_id' => $customer->id,
                'created_at' => now(),
            ],
        ];

        foreach ($reviews as $review) {
            Review::create($review);
        }
    }
}