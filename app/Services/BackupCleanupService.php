<?php

namespace App\Services;

use App\Models\DatabaseConnection;
use App\Models\BackupJob;
use Illuminate\Support\Facades\Log;

class BackupCleanupService
{
    public function cleanup(int $keepLast = null, int $olderThanDays = null): void
    {
        DatabaseConnection::with('backupJobs')->each(function ($dbConnection) use ($keepLast, $olderThanDays) {
            $backups = $dbConnection->backupJobs()->orderByDesc('created_at')->get();
            // 1. Delete backups older than X days
            if ($olderThanDays != null) {

                $cutoff = now()->subDays($olderThanDays);

                $oldBackups = $backups->filter(fn ($backup) => $backup->created_at->lt($cutoff));
                foreach ($oldBackups as $backup) {
                    $this->deleteBackup($backup, reason: 'older_than_limit');
                }

                // Refresh backups list after deletion
                $backups = $dbConnection->backupJobs()->orderByDesc('created_at')->get();
            }

            // 2. Keep only last N backups
            if ($keepLast !== null && $backups->count() > $keepLast) {
                $toDelete = $backups->slice($keepLast); // skip N, delete the rest
                foreach ($toDelete as $backup) {
                    $this->deleteBackup($backup, reason: 'exceeds_keep_last_limit');
                }
            }
        });
    }

    protected function deleteBackup(BackupJob $backup, string $reason = ''): void
    {
        $relativePath = $backup->backup_path;
        $fullPath = storage_path('app/' . $relativePath);


        // if ($path && File::exists($path)) { gpt
        if ($relativePath && file_exists($fullPath)) {
            // File::delete($path); // gpt
            unlink($fullPath);// delete file
            Log::info("Deleted backup file: {$relativePath} ({$reason})");
        } else {
            Log::warning("Backup file not found for deletion: {$relativePath}");
        }

        $backup->delete();
        Log::info("Deleted BackupJob record ID {$backup->id} ({$reason})");
    }
}