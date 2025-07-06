<?php

namespace App\Services\Adapters;

use App\Services\Contracts\DatabaseAdapterInterface;
use App\Models\DatabaseConnection;
use App\Exceptions\BackupFailedException;
use Exception;

class MySQLDatabaseAdapter implements DatabaseAdapterInterface
{

    protected DatabaseConnection $connection;

    public function __construct(DatabaseConnection $connection)
    {
        $this->connection = $connection;
    }

    public function backup(string $absolutePath)
    {
        // Ensure the backup directory exists
        $dir = dirname($absolutePath);
        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }

        $command = sprintf(
            'mysqldump -u%s -p%s -h%s -P%s %s 2>&1 > %s',
            escapeshellarg($this->connection->username),
            escapeshellarg($this->connection->password),
            escapeshellarg($this->connection->host),
            $this->connection->port ?? 3306,
            $this->connection->db_name,
            escapeshellarg($absolutePath)
            );

       // Run command
        try {
        exec($command, $output, $exitCode);
        $outputText = implode("\n", $output);

        if ($exitCode !== 0) {
            // Clean up empty file
            if (file_exists($absolutePath) && filesize($absolutePath) === 0) {
                @unlink($absolutePath);
            }

            if (stripos($outputText, 'access denied') !== false) {
                throw new BackupFailedException('Invalid database credentials.', 'invalid_credentials');
            }
            if (stripos($outputText, 'command not found') !== false) {
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
            if (stripos($outputText, 'unknown mysql server host') !== false ||
                stripos($outputText, "can't connect to mysql server") !== false) {
                throw new BackupFailedException('Cannot connect to MySQL server.', 'host_unreachable');
            }

            // Unknown issue
            throw new BackupFailedException("Backup failed with unknown error: $outputText", 'unknown');
        }

        return true;

        } catch (BackupFailedException $e) {
            // rethrow for upper-level service to handle
            throw $e;
        }
    }

    public function restore($filePath)
    {
        // Check if backup file exists
        if (!file_exists($filePath)) {
            throw new BackupFailedException("Backup file not found at: $filePath", 'file_not_found');
        }

        // Build the restore command
        $command = sprintf(
            'mysql -u%s -p%s -h%s -P%s %s < %s',
            escapeshellarg($this->connection->username),
            escapeshellarg($this->connection->password),
            escapeshellarg($this->connection->host),
            $this->connection->port ?? 3306,
            escapeshellarg($this->connection->db_name),
            escapeshellarg($filePath)
        );

        exec($command . ' 2>&1', $output, $exitCode);
        $outputText = implode("\n", $output);

        if ($exitCode !== 0) {
            // Check for common errors and throw typed exceptions if you want
            if (stripos($outputText, 'access denied') !== false) {
                throw new BackupFailedException('Invalid database credentials.', 'invalid_credentials');
            }
            if (stripos($outputText, 'permission denied') !== false) {
                throw new BackupFailedException('Permission denied while restoring database.', 'permission_denied');
            }
            if (stripos($outputText, 'unknown mysql server host') !== false ||
                stripos($outputText, "can't connect to mysql server") !== false) {
                throw new BackupFailedException('Cannot connect to MySQL server.', 'host_unreachable');
            }

            // Unknown error fallback
            throw new BackupFailedException("Restore failed: $outputText", 'unknown');
        }

        return true;
    }

    public function testConnection(): bool
    {
        $cmd = sprintf(
            'mysql -u%s -p%s -h%s -P%s -e "USE %s;"',
            escapeshellarg($this->connection->username),
            escapeshellarg($this->connection->password),
            escapeshellarg($this->connection->host),
            $this->connection->port ?? 3306,
            escapeshellarg($this->connection->db_name)
        );

        exec($cmd . ' 2>&1', $output, $exitCode);

        if ($exitCode !== 0) {
            throw new \Exception(implode("\n", $output)); // this is key
        }

        return true;
    }
}

