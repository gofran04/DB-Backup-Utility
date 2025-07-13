<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\BackupSchedule;
use App\Services\DatabaseBackupService;
use Cron\CronExpression;
use Carbon\Carbon;
use App\Factories\DatabaseAdapterFactory;
use App\Services\Compression\CompressionServiceInterface;

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
                // Use factory to resolve correct adapter
                $adapter = (new DatabaseAdapterFactory())->make($schedule->dbConnection); 
                
                // get concrete implementation that was bound to this interface
                $compressor = app(CompressionServiceInterface::class);
                
                // Run backup
                $backupService = new DatabaseBackupService($adapter,$compressor);

                $this->info("Running backup for schedule ID: {$schedule->id}");
                try {
                    $backupService->backup($schedule->dbConnection,'backups'); // assumes this method exists
                    $this->info("✅ Backup completed for connection ID: {$schedule->dbConnection->id}");
                } catch (\Exception $e) {
                    $this->error("❌ Backup failed: " . $e->getMessage());
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

