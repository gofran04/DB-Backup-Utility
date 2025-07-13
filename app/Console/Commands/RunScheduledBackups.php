<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\BackupSchedule;
use App\Services\DatabaseBackupService;
use Cron\CronExpression;
use Carbon\Carbon;
use App\Factories\DatabaseAdapterFactory;
use App\Services\Compression\CompressionServiceInterface;
use App\Models\BackupJob;
use App\Services\BackupLoggerService;
use App\Exceptions\BackupFailedException;
use App\Exceptions\DatabaseConnectionException;
use App\Exceptions\CompresionFailedException;
use App\Notifications\ScheduledBackupFailed;
use Illuminate\Support\Facades\Notification;

class RunScheduledBackups extends Command
{
    protected $signature = 'backup:schedule';
    protected $description = 'Check and run scheduled database backups';

    public function handle()
    {
        $now = Carbon::now();
        $schedules = BackupSchedule::with('dbConnection')->where('enabled', true)->get();
        
        foreach ($schedules as $schedule) 
        {
            if ($this->isDue($schedule->cron_expression, $now)) 
            {
                $backupJob = BackupJob::create([
                    'database_connection_id' => $schedule->dbConnection->id,
                    'status'                 => 'pending',
                    'mechanism'              => 'automated',
                    'started_at'             => now()
                ]);

                BackupLoggerService::logStart($backupJob);

                // Use factory to resolve correct adapter
                $adapter = (new DatabaseAdapterFactory())->make($schedule->dbConnection); 
                
                // get concrete implementation that was bound to this interface
                $compressor = app(CompressionServiceInterface::class);
                
                // Run backup
                $backupService = new DatabaseBackupService($adapter,$compressor);

                $this->info("Running backup for schedule ID: {$schedule->id}");
                try {
                    $result = $backupService->backup($schedule->dbConnection,'backups'); // assumes this method exists
                    $backupJob->update([
                        'status'       => 'completed',
                        'backup_path'  => $result['relative_path'],
                        'file_size'    => $result['file_size'],
                        'completed_at' => now()
                    ]);

                    BackupLoggerService::logSuccess($backupJob, $result['relative_path'], $result['file_size']);

                    $this->info("✅ Backup completed for connection ID: {$schedule->dbConnection->id}");
                } catch (DatabaseConnectionException | BackupFailedException | CompresionFailedException $e) {
                    $backupJob->update([
                        'status'        => 'failed',
                        'error_message' => $e->getMessage(),
                        'completed_at'  => now()
                    ]);

                    BackupLoggerService::logFailure($backupJob, $e);
                    $this->error("❌ Backup failed: " . $e->getMessage());

                    // Send alert via email
                    Notification::route('mail', 'admin@example.com')
                        ->notify(new ScheduledBackupFailed($backupJob));
                }
            }
        }

        return Command::SUCCESS;
    }

    private function isDue(string $cronExpression, Carbon $now): bool
    {
        $cron = CronExpression::factory($cronExpression);
        return $cron->isDue($now);
    }
}

