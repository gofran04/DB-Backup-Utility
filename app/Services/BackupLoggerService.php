<?php

namespace App\Services;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class BackupLoggerService
{
    public static function logStart($job)
    {
        // Log at the start of backup
        Log::info('ss Backup job started', [
            'backup_job_id' => $job->id,
            'db_connection_id' => $job->database_connection_id,
            'db_name' => $job->databaseConnection->db_name,
        ]);
    }

    public static function logSuccess($job, $filePath, $fileSize)
    {
        Log::info('cc Backup job completed successfully', [
            'backup_job_id' => $job->id,
            'file_path' => $filePath,
            'file_size' => $fileSize,
            'duration' => now()->diffInSeconds($job->started_at),
        ]);
    }

    public static function logFailure($job, \Throwable $e)
    {
        Log::error('ff Backup job failed', [
            'backup_job_id' => $job->id,
            'db_connection_id' => $job->database_connection_id,
            'error' => $e->getMessage(),
            'duration' => now()->diffInSeconds($job->started_at),
            'trace' => $e->getTraceAsString(),
        ]);
    }
}
