<?php

namespace App\Console\Commands;

use App\Models\RefreshToken;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CleanupExpiredTokens extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tokens:cleanup {--days=7 : Delete tokens older than X days}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cleanup expired and old refresh tokens from database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $daysOld = $this->option('days');
        $cutoffDate = Carbon::now()->subDays($daysOld);
        
        $this->info("Cleaning up refresh tokens older than {$daysOld} days (before {$cutoffDate->format('Y-m-d H:i:s')})...");
        
        $expiredCount = RefreshToken::where('expire_at', '<', Carbon::now())
            ->orWhere('status', RefreshToken::STATUS_EXPIRED)
            ->delete();
            
        $revokedCount = RefreshToken::where('status', RefreshToken::STATUS_REVOKED)
            ->where('revoked_at', '<', $cutoffDate)
            ->delete();
            
        $oldActiveCount = RefreshToken::where('status', RefreshToken::STATUS_ACTIVE)
            ->where('created_at', '<', $cutoffDate)
            ->delete();
        
        $totalDeleted = $expiredCount + $revokedCount + $oldActiveCount;
        
        $this->info("Cleanup completed:");
        $this->line("- Expired tokens deleted: {$expiredCount}");
        $this->line("- Old revoked tokens deleted: {$revokedCount}");
        $this->line("- Old active tokens deleted: {$oldActiveCount}");
        $this->line("- Total deleted: {$totalDeleted}");
        
        Log::info("Refresh tokens cleanup completed", [
            'expired_deleted' => $expiredCount,
            'revoked_deleted' => $revokedCount,
            'old_active_deleted' => $oldActiveCount,
            'total_deleted' => $totalDeleted,
            'cutoff_date' => $cutoffDate->toDateTimeString()
        ]);
        
        return 0;
    }
}
