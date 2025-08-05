<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BackupCleanupService;
use Illuminate\Support\Facades\Log;

class BackupCleanup extends Command
{
    protected $signature = 'backup:cleanup {--keep-last=} {--older-than-days=}';
    protected $description = 'Cleanup old backups by either:--keep-last=N (keep last N backups) OR --older-than-days=N (delete backups older than N days)';
   

    public function __construct(protected BackupCleanupService $backupCleanupService) 
    {
        parent::__construct();
    }

    public function handle()
    {
        $keepLast =  $this->option('keep-last');
        $olderThanDays =  $this->option('older-than-days');

        if (($keepLast !== null && $olderThanDays !== null) || ($keepLast == null && $olderThanDays == null)){
            $this->error('❌ You must provide either --keep-last OR --older-than-days (but not both)');
            return Command::INVALID;
        }

        try {
            if ($keepLast !== null) 
            {
                if (!is_numeric($keepLast) || (int)$keepLast < 1) {
                    $this->error('❌ --keep-last must be a positive integer greater than 0');
                    return Command::INVALID;
                }
                $this->info("🧹 Cleaning up: Keeping only last {$keepLast} backups...");
                $this->backupCleanupService->cleanup($keepLast, null);
            }
            if($olderThanDays !== null) {
                if (!is_numeric($olderThanDays) || (int)$olderThanDays < 1) {
                    $this->error('❌ --older-than-days must be a positive integer greater than 0');
                    return Command::INVALID;
                }
                $this->info("🧹 Cleaning up: Removing backups older than {$olderThanDays} days...");
                $this->backupCleanupService->cleanup(null, $olderThanDays);
            }

            $this->info("✅ Cleanup completed.");
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("❌ Cleanup failed: " . $e->getMessage());
            Log::error("Backup cleanup failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }
}
