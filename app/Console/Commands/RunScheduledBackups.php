<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\BackupSchedule;
use Cron\CronExpression;
use Carbon\Carbon;
use App\Models\BackupJob;
use App\Services\BackupLoggerService;
use App\Exceptions\BackupFailedException;
use App\Exceptions\DatabaseConnectionException;
use App\Exceptions\CompresionFailedException;
use App\Notifications\ScheduledBackupFailed;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Log;
use App\Jobs\ProcessDatabaseBackup;

class RunScheduledBackups extends Command
{
    protected $signature = 'backup:schedule';
    protected $description = 'Check and run scheduled database backups';

    public function handle()
    {
        $now = Carbon::now();
        $schedules = BackupSchedule::with('dbConnection')->where('enabled', true)->get();
        
        if(!$schedules->isEmpty()){
            foreach ($schedules as $schedule) 
            {
                if ($this->isDue($schedule->cron_expression, $now)) 
                {
                    $backupJob = null;

                    if(!is_null($schedule->db_connection_id)){
                        $backupJob = BackupJob::create([
                            'database_connection_id' => $schedule->dbConnection->id,
                            'status'                 => 'pending',
                            'mechanism'              => 'automated',
                            'started_at'             => now()
                        ]);
                    }elseif(!is_null($schedule->profile_name)){
                        $backupJob = BackupJob::create([
                            'profile_name' => $schedule->profile_name,
                            'status'       => 'pending',
                            'mechanism'    => 'automated',
                            'started_at'   => now()
                        ]);
                    }

                    try {
                        Log::info("📦 Dispatching backup job (CLI), Job ID: ". $backupJob->id);
        
                        // Dispatch the queued job
                        ProcessDatabaseBackup::dispatch($backupJob->id)->onQueue('backups');

                        $this->info("✅ Backup job queued successfully (Job ID: {$backupJob->id})");
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
        }else{
            $this->info('There Are No Scheduled Backups To Run.');
            return 0;
        }
    }

    private function isDue(string $cronExpression, Carbon $now): bool
    {
        $cron = CronExpression::factory($cronExpression);
        return $cron->isDue($now);
    }
}