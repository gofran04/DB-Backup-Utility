<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BackupStorageService;

class BackupStorageReport extends Command
{
    protected $signature = 'backup-storage-report';
    protected $description = 'Command to show: how much space backups are using,total backup files count,total size in MB/GB and largest and smallest backup';

    public function __construct(protected BackupStorageService $backup_storage_service) 
    {
        parent::__construct();
    }

    public function handle()
    {
        $report = $this->backup_storage_service->getStorageReport();

        if (empty($report)) {
            $this->info("No backup files found.");
            return Command::SUCCESS;
        }

        $this->info("🗂 Total Backups: {$report['count']}");
        $this->info("📦 Total Size: " . $this->formatBytes($report['total_size']));
        $this->info("⬆️ Largest: {$report['largest']['path']} (" . $this->formatBytes($report['largest']['size']) . ")");
        $this->info("⬇️ Smallest: {$report['smallest']['path']} (" . $this->formatBytes($report['smallest']['size']) . ")");

        return Command::SUCCESS;    
    }

    protected function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $power = floor(($bytes ? log($bytes) : 0) / log(1024));
        $power = min($power, count($units) - 1);

        $bytes /= pow(1024, $power);
        return round($bytes, $precision) . ' ' . $units[$power];
    }
}
