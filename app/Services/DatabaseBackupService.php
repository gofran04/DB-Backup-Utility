<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Config;

class DatabaseBackupService
{
    public static function backup($connectionName, $outputPath)
    {
        // Get connection config from memory
        $config = config("database.connections.{$connectionName}");

        // File name format like: client_db_backup_20250613_162510.sql
        $filename = "{$config['database']}_backup_" . date('Ymd_His') . ".sql";
        $relativePath = "{$outputPath}/{$filename}";
        $fullPath = storage_path($relativePath);

        // Build mysqldump command
        $command = sprintf(
            'mysqldump --user=%s --password=%s --host=%s --port=%s %s > %s 2>&1',
            escapeshellarg($config['username']),
            escapeshellarg($config['password']),
            escapeshellarg($config['host']),
            escapeshellarg($config['port'] ?? '3306'),
            escapeshellarg($config['database']),
            escapeshellarg($fullPath)
        );

        // Execute it ($command)
        $result = null;
        $output = [];
        exec($command, $output, $result);

        // wait 100 ms.to fully writing the file to avoid getting wronge size
        usleep(100000); 

        // Success
        if ($result === 0 && file_exists($fullPath)) {
            return [
                'status'    => true,
                'file_path' => $fullPath,
                'file_size' => filesize($fullPath),
            ];
        }

        // Failure
        return [
            'status'    => false,
            'error'     => implode("\n", $output),
        ];
    }
}
