<?php
namespace App\Services;

use App\Exceptions\BackupFailedException;
use App\Services\TestDatabaseConnectionService;
use App\Traits\ApiResponseTrait;
use Illuminate\Support\Facades\Log;

class DatabaseBackupService
{
    use ApiResponseTrait;

    public function backup($db_id, $outputPath)
    {
        // Firstly test DB Connection
        $result = TestDatabaseConnectionService::createDynamicConnection($db_id);
        $connectionName = $result['connectionName'];

        // Get connection config from memory
        $config = config("database.connections.{$connectionName}");

        // File name format like: client_db_backup_20250613_162510.sql
        $filename = "{$config['database']}_backup_" . date('Ymd_His') . ".sql";
        $relativePath = "{$outputPath}/{$filename}";
        $fullPath = storage_path('app/' . $relativePath);

        // Build mysqldump command to output to stdout (no `> file`)
        $command = sprintf(
            'mysqldump --user=%s --password=%s --host=%s --port=%s %s 2>&1 > %s',
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
        if ($result !== 0 ) 
        {
            // On failure:
            Log::error('CLI Backup failed', [
                'db_id'   => $db_id,
                // 'error'            => $e->getMessage(),
                // 'trace'            => $e->getTraceAsString()
            ]);

            if (stripos($outputText, 'access denied') !== false) {
                throw new BackupFailedException('Invalid database credentials.', 'invalid_credentials');
            }
            if (stripos($outputText, 'sh: 1: mysqldump_fake: not found') !== false ||  stripos($outputText, 'command not found') !== false) {
                throw new BackupFailedException('mysqldump command not found.', 'mysqldump_missing');
            }
            if (stripos($outputText, 'permission denied') !== false) {
                throw new BackupFailedException('Permission denied while writing backup file.', 'permission_denied');
            }
            if (stripos($outputText, 'no space left on device') !== false) {
                throw new BackupFailedException('Insufficient disk space for backup.', 'disk_full');
            }
            if (stripos($outputText, 'timed out') !== false) {
                throw new BackupFailedException('Database backup operation timed out.', 'timeout');
            }
            if (stripos($outputText, 'unknown mysql server host') !== false) {
                throw new BackupFailedException('Unknown MySQL server host.', 'host_unreachable');
            }
            if (stripos($outputText, "can't connect to mysql server") !== false) {
                throw new BackupFailedException('Cannot connect to MySQL server on the specified host.', 'host_unreachable');
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

    public function backupUsingProfile($config,$outputPath)
    {
        // First: test connection dynamically
        TestDatabaseConnectionService::testProfileConnection($config);
        
        // File name format
        $filename = "{$config['database']}_backup_" . date('Ymd_His') . ".sql";
        $relativePath = "{$outputPath}/{$filename}";
        $fullPath = storage_path('app/' . $relativePath);

        $command = sprintf(
            'mysqldump --user=%s --password=%s --host=%s --port=%s %s 2>&1 > %s',
            escapeshellarg($config['username']),
            escapeshellarg($config['password']),
            escapeshellarg($config['host']),
            escapeshellarg($config['port'] ?? '3306'),
            escapeshellarg($config['database']),
            escapeshellarg($fullPath)
        );

        $output = [];
        $result = 0;
        exec($command, $output, $result);

        $outputText = implode("\n", $output);

        if ($result !== 0) {
            // same error checks as before
            if (stripos($outputText, 'access denied') !== false) {
                throw new BackupFailedException('Invalid database credentials.', 'invalid_credentials');
            }
            if (stripos($outputText, 'mysqldump') !== false && stripos($outputText, 'not found') !== false) {
                throw new BackupFailedException('mysqldump command not found.', 'mysqldump_missing');
            }
            if (stripos($outputText, 'permission denied') !== false) {
                throw new BackupFailedException('Permission denied while writing backup file.', 'permission_denied');
            }
            if (stripos($outputText, 'no space left') !== false) {
                throw new BackupFailedException('Insufficient disk space for backup.', 'disk_full');
            }
            if (stripos($outputText, 'timed out') !== false) {
                throw new BackupFailedException('Database backup operation timed out.', 'timeout');
            }
            if (stripos($outputText, 'unknown mysql server host') !== false) {
                throw new BackupFailedException('Unknown MySQL server host.', 'host_unreachable');
            }
            if (stripos($outputText, "can't connect to mysql server") !== false) {
                throw new BackupFailedException('Cannot connect to MySQL server on the specified host.', 'host_unreachable');
            }

            throw new BackupFailedException("Backup failed: $outputText", 'unknown');
        }

        $fileSize = file_exists($fullPath) ? filesize($fullPath) : 0;

        if ($result === 0 && $fileSize > 0) {
            return [
                'status'    => true,
                'file_path' => $relativePath,
                'file_size' => $fileSize,
            ];
        }
    }
}
