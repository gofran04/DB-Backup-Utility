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

        if ($jobs->isEmpty()) {
            $this->warn('⚠️  No automated backup jobs found.');
            return Command::SUCCESS;
        }
        
        foreach ($jobs as $job) {
            // display job info here
            $this->line("Backup ID: {$job->id}");
            $this->line("Status: {$job->status}");
            $this->line("Started At: {$job->started_at}");
            $this->line("Completed At: {$job->completed_at}");
            $this->line("File Size: {$job->file_size}");
            if ($job->error_message) {
                $this->error("Error: {$job->error_message}");
            }
            $this->line("--------------------");
        }
        return Command::SUCCESS;
    }
}
