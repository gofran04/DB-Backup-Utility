<?php
namespace App\Services;

use App\Exceptions\BackupFailedException;

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
        $result = 0;
        exec($command, $output, $result);

        $outputText = implode("\n", $output);

        // Analyze common error cases
        if ($result !== 0 || str_starts_with($outputText, 'mysqldump:')) 
        {
            if (str_contains($outputText, 'command not found')) {
                throw new BackupFailedException('mysqldump command not found.', 'mysqldump_missing');
            }
            if (str_contains($outputText, 'Permission denied')) {
                throw new BackupFailedException('Permission denied while writing backup file.', 'permission_denied');
            }
            if (str_contains($outputText, 'No space left on device')) {
                throw new BackupFailedException('Insufficient disk space for backup.', 'disk_full');
            }
            if (str_contains($outputText, 'timed out')) {
                throw new BackupFailedException('Database backup operation timed out.', 'timeout');
            }
            throw new BackupFailedException("Backup failed: $outputText", 'unknown');
        }

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
    }
}
