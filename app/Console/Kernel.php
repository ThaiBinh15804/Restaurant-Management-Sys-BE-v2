<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Log;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        \App\Console\Commands\CheckExpiredPromotions::class,
    ];

    protected function schedule(Schedule $schedule)
    {
        $schedule->command('promotions:check-expired')->everyMinute();
        $schedule->call(function () {
            Log::info('Scheduler test: ' . now());
        })->everyMinute();
    }

    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');
        require base_path('routes/console.php');
    }
}
