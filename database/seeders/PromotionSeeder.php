<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Promotion;

class PromotionSeeder extends Seeder
{
    public function run(): void
    {
        $promotions = [
            [
                'code' => 'WELCOME10',
                'description' => 'Giảm 10% cho đơn đầu tiên',
                'discount_percent' => 10,
                'start_date' => now()->toDateString(),
                'end_date' => now()->addDays(30)->toDateString(),
                'usage_limit' => 100,
                'used_count' => 0,
                'is_active' => true,
            ],
            [
                'code' => 'SUMMER15',
                'description' => 'Giảm 15% cho đơn hàng mùa hè',
                'discount_percent' => 15,
                'start_date' => now()->toDateString(),
                'end_date' => now()->addDays(45)->toDateString(),
                'usage_limit' => 200,
                'used_count' => 0,
                'is_active' => true,
            ],
            [
                'code' => 'FREESHIP50',
                'description' => 'Miễn phí vận chuyển cho đơn trên 50k',
                'discount_percent' => 0,
                'start_date' => now()->toDateString(),
                'end_date' => now()->addDays(60)->toDateString(),
                'usage_limit' => 300,
                'used_count' => 0,
                'is_active' => true,
            ],
            [
                'code' => 'VIP20',
                'description' => 'Giảm 20% cho khách hàng VIP',
                'discount_percent' => 20,
                'start_date' => now()->toDateString(),
                'end_date' => now()->addDays(90)->toDateString(),
                'usage_limit' => 50,
                'used_count' => 0,
                'is_active' => true,
            ],
            [
                'code' => 'NEWYEAR25',
                'description' => 'Giảm 25% nhân dịp năm mới',
                'discount_percent' => 25,
                'start_date' => now()->toDateString(),
                'end_date' => now()->addDays(120)->toDateString(),
                'usage_limit' => 500,
                'used_count' => 0,
                'is_active' => true,
            ],
        ];

        foreach ($promotions as $promotion) {
            Promotion::create($promotion);
        }
    }
}
