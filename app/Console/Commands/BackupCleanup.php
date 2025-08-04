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
        $keepLast = (int) $this->option('keep-last');
        $olderThanDays = (int) $this->option('older-than-days');

        if (($keepLast && $olderThanDays) || (!$keepLast && !$olderThanDays)) {
            $this->error('❌ You must provide either --keep-last OR --older-than-days (but not both)');
            return Command::INVALID;
        }

        try {
            if ($keepLast) {
                $this->info("🧹 Cleaning up: Keeping only last {$keepLast} backups...");
                $this->backupCleanupService->cleanup($keepLast, null);
            } else {
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
