<?php

namespace App\Services;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class BackupLoggerService
{
    public static function logStart($job)
    {
        // Log at the start of backup
        Log::info('Backup job started', [
            'backup_job_id'    => $job->id,
            'db_name'          => self::getDbName($job),
        ]);
    }

    public static function logSuccess($job, $filePath, $fileSize)
    {
        Log::info('Backup job completed successfully', [
            'backup_job_id' => $job->id,
            'file_path'     => $filePath,
            'file_size'     => $fileSize,
            'duration'      => now()->diffInSeconds($job->started_at),
        ]);
    }

    public static function logFailure($job, \Throwable $e)
    {
        Log::error('Backup job failed', [
            'backup_job_id'    => $job->id,
            'db_name'          => self::getDbName($job),
            'error'            => $e->getMessage(),
            'duration'         => now()->diffInSeconds($job->started_at),
            'trace'            => $e->getTraceAsString(),
        ]);
    }

    private static function getDbName($job)
    {
        if ($job->database_connection_id) {
            return $job->databaseConnection->db_name;
        }

        if ($job->profile_name) {
            $configService = app(\App\Services\ConfigService::class);
            $profiles = $configService->loadProfiles();
            return $profiles[$job->profile_name]['database'];
        }

        return null;
    }
}
