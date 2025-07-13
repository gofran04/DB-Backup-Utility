<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\BackupJob;

class BackupStatus extends Command
{
    protected $signature = 'backup:status';
    protected $description = 'Show the latest backup job for each schedule';

    public function handle()
    {
        $jobs = BackupJob::where('mechanism', 'automated')->orderBy('created_at', 'desc')->get();

        foreach ($jobs as $job) {
            // display job info here
            echo "Backup ID: {$job->id}\n";
            echo "Status: {$job->status}\n";
            echo "Started At: {$job->started_at}\n";
            echo "Completed At: {$job->completed_at}\n";
            echo "File Size: {$job->file_size}\n";
            if ($job->error_message) {
                echo "Error: {$job->error_message}\n";
            }
            echo "--------------------\n";
        }
        return Command::SUCCESS;
    }
}
