<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Config;

class DatabaseBackupService
{
    public function backup($connectionName, $outputPath)
    {
        $config = config("database.connections.{$connectionName}");

        $filename = "{$config['database']}_backup_" . date('Ymd_His') . ".sql";
        $fullPath = "{$outputPath}/{$filename}";

        $command = sprintf(
            'mysqldump -h%s -P%s -u%s -p%s %s > %s',
            escapeshellarg($config['host']),
            escapeshellarg($config['port'] ?? '3306'),
            escapeshellarg($config['username']),
            escapeshellarg($config['password']),
            escapeshellarg($config['database']),
            escapeshellarg(storage_path($fullPath))
        );

        $result = null;
        $output = [];
        exec($command, $output, $result);

        if ($result === 0) {
            return [
                'status' => true,
                'file' => storage_path($fullPath),
            ];
        }

        return [
            'status' => false,
            'error' => implode("\n", $output),
        ];
    }
}
