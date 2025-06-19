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
        $fullPath = storage_path('app/' . $relativePath);

        // Build mysqldump command to output to stdout (no `> file`)
        $command = sprintf(
            'mysqldump --user=%s --password=%s --host=%s --port=%s %s > %s 2>&1',
            escapeshellarg($config['username']),
            escapeshellarg($config['password']),
            escapeshellarg($config['host']),
            escapeshellarg($config['port'] ?? '3306'),
            escapeshellarg($config['database']),
            escapeshellarg($fullPath)
        );

         // Run command
        $output = [];
        $result = null;
        exec($command, $output, $result);

        // Check if file was created and has content(avvoid getting size = 0)
        $fileSize = file_exists($fullPath) ? filesize($fullPath) : 0;

        // Success
        if ($result === 0 && $fileSize > 0) {
            return [
                'status'          => true,
                'file_path'       => $relativePath,
                'file_size'       => $fileSize,
            ];
        }

        // Failure
        return [
            'status'    => false,
            'error'     => implode("\n", $output),
        ];
    }
}
