<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use App\Models\BackupJob;
use App\Models\DatabaseConnection;
use App\Factories\DatabaseAdapterFactory;
use App\Services\DatabaseBackupService;
use App\Services\BackupLoggerService;
use App\Services\Compression\CompressionServiceInterface;
use App\Exceptions\DatabaseConnectionException;
use App\Exceptions\BackupFailedException;
use App\Exceptions\CompresionFailedException;
use App\Traits\ApiResponseTrait;
use App\Services\ConfigService;

class ProcessDatabaseBackup implements ShouldQueue
{
    use Queueable, ApiResponseTrait;

    public $tries = 2; // Retry 2 times if fails
    public $timeout = 600; // 10 minutes (the max execution time)

    public function __construct(protected $backupJobId)
    {
    
    }

    public function handle(DatabaseAdapterFactory $adapterFactory,CompressionServiceInterface $compressor,ConfigService $configService): void
    {
        $backupJob = BackupJob::findOrFail($this->backupJobId);

        try {
            BackupLoggerService::logStart($backupJob);

            $adapterFactory = app(DatabaseAdapterFactory::class);
            $compressor = app(CompressionServiceInterface::class);

            $path = config('backup.storage_path') . '/backups';
            $backupResult = null;

            if ($backupJob->database_connection_id) {
                // Case: backup using db_id
                $dbConnection = DatabaseConnection::findOrFail($backupJob->database_connection_id);
                $adapter = $adapterFactory->make($dbConnection);
                $backupService = new DatabaseBackupService($adapter, $compressor);

                $backupResult = $backupService->backupUsingDbId($dbConnection, $path);

            } elseif ($backupJob->profile_name) {
                // Case: backup using profile
                $configService = app(ConfigService::class);;
                $profiles = $configService->loadProfiles();
                $profile = $profiles[$backupJob->profile_name];

                $adapter = $adapterFactory->makeFromProfile($profile);
                $backupService = new DatabaseBackupService($adapter, $compressor);

                $backupResult = $backupService->backupUsingProfile($profile, $path);
            }

        $backupJob->update([
            'status'       => 'completed',
            'backup_path'  => $backupResult['relative_path'],
            'file_size'    => $backupResult['file_size'],
            'completed_at' => now()
        ]);

        BackupLoggerService::logSuccess($backupJob, $backupResult['relative_path'], $backupResult['file_size']);

        } catch (DatabaseConnectionException $e) {
            $this->handleFailure($backupJob, $e, 'Database connection failed');

        } catch (BackupFailedException $e) {
            $this->handleFailure($backupJob, $e, 'Backup failed');

        } catch (CompresionFailedException $e) {
            $this->handleFailure($backupJob, $e, 'Compression failed');

        } catch (\Exception $e) {
            $this->handleFailure($backupJob, $e, 'Unexpected error');
            throw $e; // Let Laravel mark as failed job
        }
    }

    protected function handleFailure($backupJob, $e, $context)
    {
        $backupJob->update([
            'status'        => 'failed',
            'error_message' => $e->getMessage(),
            'completed_at'  => now()
        ]);

        BackupLoggerService::logFailure($backupJob, $e);
        Log::error("{$context}: " . $e->getMessage());
        throw $e; // Trigger retry/failure in Laravel
    }

    public function failed(\Throwable $exception)
    {
        Log::error("Backup job ID {$this->backupJobId} failed after all retries: " . $exception->getMessage());
    }
}
