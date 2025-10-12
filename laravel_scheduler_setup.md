# 🕒 Hướng dẫn cài đặt Laravel Scheduler chạy tự động bằng Windows Task Scheduler

## 📘 Mục tiêu
Thiết lập hệ thống chạy tự động lệnh `php artisan schedule:run` mỗi giờ hoặc mỗi ngày, để kiểm tra và cập nhật trạng thái (ví dụ: khuyến mãi hết hạn) trong project Laravel.

---

## 🧩 1. Cấu hình Laravel Scheduler

### 1.1. Tạo Command tùy chỉnh

Chạy lệnh:
```bash
php artisan make:command CheckExpiredPromotions
```

Lệnh này sẽ tạo file tại:
```
app/Console/Commands/CheckExpiredPromotions.php
```

### 1.2. Viết logic kiểm tra khuyến mãi hết hạn

Mở file vừa tạo và thêm nội dung:
```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Promotion;
use Carbon\Carbon;

class CheckExpiredPromotions extends Command
{
    protected $signature = 'promotions:check-expired';
    protected $description = 'Tự động kiểm tra và vô hiệu hóa các khuyến mãi đã hết hạn';

    public function handle()
    {
        $now = Carbon::now();

        $expiredPromotions = Promotion::where('end_date', '<', $now)
            ->where('is_active', true)
            ->get();

        foreach ($expiredPromotions as $promotion) {
            $promotion->update(['is_active' => false]);
        }

        $this->info("Đã vô hiệu hóa {$expiredPromotions->count()} khuyến mãi hết hạn.");
    }
}
```

---

## ⚙️ 2. Cấu hình Kernel

Mở file:
```
app/Console/Kernel.php
```

Thêm command vào `schedule()`:

```php
protected function schedule(Schedule $schedule): void
{
    // Chạy mỗi giờ
    $schedule->command('promotions:check-expired')->hourly();

    // Hoặc nếu muốn chạy mỗi ngày lúc 13:42
    // $schedule->command('promotions:check-expired')->dailyAt('13:42');
}
```

Đảm bảo command được đăng ký:
```php
protected $commands = [
    \App\Console\Commands\CheckExpiredPromotions::class,
];
```

---

## 🧪 3. Kiểm tra chạy thủ công

Chạy thử trong terminal:
```bash
php artisan schedule:run
```

Nếu command hoạt động đúng, bạn sẽ thấy:
```
Running scheduled command: promotions:check-expired
Đã vô hiệu hóa X khuyến mãi hết hạn.
```

---

## 🪟 4. Thiết lập Windows Task Scheduler

### 4.1. Mở Task Scheduler
- Nhấn **Windows + S**
- Gõ **Task Scheduler**
- Mở ứng dụng.

### 4.2. Tạo Task mới
Chọn:
```
Action → Create Task
```

### 4.3. Tab "General"
- **Name:** Laravel Scheduler  
- **Description:** Tự động chạy schedule cho Laravel  
- **Run whether user is logged on or not**  
- **Run with highest privileges**  
- **Configure for:** Windows 10/11

---

### 4.4. Tab "Triggers"
- Nhấn **New**
- Chọn **Daily** hoặc **Repeat task every 1 hour**
- Ví dụ: Start time `13:42:00`
- Nhấn **OK**

---

### 4.5. Tab "Actions"
- Nhấn **New**
- **Action:** Start a program
- **Program/script:**
  ```
  php
  ```
- **Add arguments:**
  ```
  artisan schedule:run
  ```
- **Start in (optional):**
  ```
  C:\laragon\www\Restaurant-Management-Sys-BE-v2
  ```
  *(Thay bằng đường dẫn project Laravel của bạn)*

Nhấn **OK** để lưu.

---

### 4.6. Tab "Conditions" & "Settings"
- Bỏ chọn "Start the task only if the computer is on AC power"
- Chọn "Run task as soon as possible after a scheduled start is missed"

---

### 4.7. Kiểm tra Task
- Vào **Task Scheduler Library**
- Tìm task `Laravel Scheduler`
- Chuột phải → **Run** để chạy thử.

Kiểm tra log trong Laravel:
```
storage/logs/laravel.log
```
hoặc bật tab **History** để xem kết quả chạy.

---

## 🧾 5. (Tuỳ chọn) Ghi log ra file riêng

Bạn có thể sửa Kernel để ghi log mỗi khi chạy:
```php
$schedule->command('promotions:check-expired')
    ->hourly()
    ->appendOutputTo(storage_path('logs/scheduler.log'));
```

---

## ✅ Kết quả
- Mỗi ngày hoặc mỗi giờ, hệ thống tự động chạy command `promotions:check-expired`.
- Khuyến mãi hết hạn được tự động cập nhật `is_active = false`.
- Không cần thao tác thủ công.

---

## 📌 Gỡ lỗi thường gặp
| Lỗi | Cách khắc phục |
|------|----------------|
| Task không chạy | Kiểm tra đường dẫn PHP và project đúng chưa |
| “php” không nhận | Dùng đường dẫn đầy đủ: `C:\laragon\bin\php\php-8.3.0-Win32-vs16-x64\php.exe` |
| Không có log | Thêm `->appendOutputTo(storage_path('logs/scheduler.log'))` |
| Command không thấy | Chạy `php artisan list` để đảm bảo command được đăng ký |

---

**Tác giả:** Phạm Minh Thuận  
**Cập nhật:** 12/10/2025  
**Mục đích:** Tự động hoá xử lý dữ liệu định kỳ trong hệ thống nhà hàng Laravel.
