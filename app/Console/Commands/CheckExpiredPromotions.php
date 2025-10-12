<?php

namespace App\Console\Commands;

use App\Models\Promotion;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CheckExpiredPromotions extends Command
{
    // Tên của command (dùng khi gọi thủ công)
    protected $signature = 'promotions:check-expired';

    protected $description = 'Tự động kiểm tra và vô hiệu hóa các khuyến mãi đã hết hạn';

    public function handle()
    {
        $now = Carbon::now();

        $expiredPromotions = Promotion::where('is_active', true)
            ->where('end_date', '<', $now)
            ->update(['is_active' => false]);

        $this->info("✅ Đã vô hiệu hóa {$expiredPromotions} khuyến mãi hết hạn.");

        return 0;
    }
}
