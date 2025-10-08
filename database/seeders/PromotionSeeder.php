<?php

namespace Database\Seeders;

use App\Models\Promotion;
use Illuminate\Database\Seeder;

class PromotionSeeder extends Seeder
{
    public function run(): void
    {
        $promotions = [
            [
                'id' => 'PROMO1',
                'code' => 'LUNCH30',
                'description' => '30% off for lunch time',
                'discount_percent' => 30.00,
                'start_date' => '2025-10-01',
                'end_date' => '2025-10-31',
                'usage_limit' => 100,
                'is_active' => true,
            ],
        ];

        foreach ($promotions as $promotion) {
            Promotion::create($promotion);
        }
    }
}