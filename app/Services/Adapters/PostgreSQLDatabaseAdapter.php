<?php

namespace App\Services\Adapters;

use App\Services\Contracts\DatabaseAdapterInterface;
use App\Models\DatabaseConnection;
use App\Exceptions\BackupFailedException;
use Exception;

class PostgreSQLDatabaseAdapter implements DatabaseAdapterInterface
{
    protected DatabaseConnection $connection;

    public function __construct(DatabaseConnection $connection)
    {
        $this->connection = $connection;
    }

    public function backup(string $outputPath): bool
    {
        // Ensure the backup directory exists
        $dir = dirname($outputPath);
        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }

        $cmd = sprintf(
            'PGPASSWORD=%s /usr/bin/pg_dump -U %s -h %s -p %d -F p %s 2>&1',
            escapeshellarg($this->connection->password),
            escapeshellarg($this->connection->username),
            escapeshellarg($this->connection->host),
            $this->connection->port ?? 5432,
            escapeshellarg($this->connection->db_name),
            escapeshellarg($outputPath)
        );

        try {
            $output = [];
            $exitCode = 0;
            exec($cmd, $output, $exitCode);
            $outputText = implode("\n", $output);

            if ($exitCode !== 0){
                // Clean up empty file
                if (file_exists($outputPath) && filesize($outputPath) === 0) {
                    @unlink($outputPath);
                }

                if (
                    preg_match('/password authentication failed/i', $outputText) ||
                    preg_match('/role\s+".+"\s+does not exist/i', $outputText) ||
                    preg_match('/FATAL:\s+.*authentication/i', $outputText)
                ) {
                    throw new BackupFailedException('Invalid PostgreSQL credentials or role does not exist.', 'invalid_credentials');
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

            // Write to file only after success
            file_put_contents($outputPath, implode("\n", $output));
            
            return true;
        } catch (Exception $e) {
            // rethrow for upper-level service to handle
            throw $e;        
        }
    }

    public function restore(string $filePath)
    {
        $cmd = sprintf(
            'PGPASSWORD=%s /usr/bin/psql -U %s -h %s -p %d -d %s -f %s 2>&1',
            escapeshellarg($this->connection->password),
            escapeshellarg($this->connection->username),
            escapeshellarg($this->connection->host),
            $this->connection->port ?? 5432,
            escapeshellarg($this->connection->db_name),
            escapeshellarg($filePath)
        );

        try {
            $output = [];
            $exitCode = 0;
            exec($cmd, $output, $exitCode);
            $outputText = implode("\n", $output);

            if ($exitCode !== 0) {
                // Detect PostgreSQL-specific errors
                if (
                    stripos($outputText, 'password authentication failed') !== false ||
                    stripos($outputText, 'role') !== false && stripos($outputText, 'does not exist') !== false ||
                    stripos($outputText, 'FATAL') !== false && stripos($outputText, 'authentication') !== false
                ) {
                    throw new BackupFailedException('Invalid PostgreSQL credentials or role does not exist.', 'invalid_credentials');
                }

                if (stripos($outputText, 'permission denied') !== false) {
                    throw new BackupFailedException('Permission denied while restoring database.', 'permission_denied');
                }

                if (stripos($outputText, 'No such file') !== false) {
                    throw new BackupFailedException('Backup file does not exist.', 'file_missing');
                }

                throw new BackupFailedException("Restore failed: $outputText", 'unknown');
            }

            return true;
        } catch (Exception $e) {
            throw $e;
        }
    }


    public function testConnection()
    {
        $cmd = sprintf(
            'PGPASSWORD=%s pg_isready -U %s -h %s -p %d',
            escapeshellarg($this->connection->password),
            escapeshellarg($this->connection->username),
            escapeshellarg($this->connection->host),
            $this->connection->port ?? 5432
        );

        exec($cmd, $output, $exitCode);
        if ($exitCode !== 0) {
            throw new \Exception(implode("\n", $output)); // this is key
        }

        return $exitCode === 0;
    }
}
