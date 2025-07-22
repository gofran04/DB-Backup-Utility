<?php

namespace App\Services\Adapters;

use App\Services\Contracts\DatabaseAdapterInterface;
use App\Models\DatabaseConnection;
use App\Exceptions\BackupFailedException;
use App\Exceptions\DatabaseConnectionException;
use App\Exceptions\RestoreFailedException;
use Illuminate\Support\Facades\Crypt;

class MySQLDatabaseAdapter implements DatabaseAdapterInterface
{
    protected string $host;
    protected string $port;
    protected string $username;
    protected string $password;
    protected string $db_name;

    public function __construct(DatabaseConnection|array $connection)
    {
        if (is_array($connection)) {
            $this->host = $connection['host'];
            $this->port = $connection['port'];
            $this->username = $connection['username'];
            $this->password = $connection['password'];// already decrypted
            $this->db_name = $connection['database'];
        } else {
            $this->host = $connection->host;
            $this->port = $connection->port;
            $this->username = $connection->username;
            $this->password = Crypt::decryptString($connection->password);
            $this->db_name = $connection->db_name;
        }
    }

    public function backupViaDbId(string $absolutePath)
    {
        // Ensure the backup directory exists
        $dir = dirname($absolutePath);
        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }

        // Step 1: Generate temporary .cnf file
        $tempCnf = tempnam(sys_get_temp_dir(), 'mycnf_');

        $configg = <<<CNF
        [client]
        user={$this->username}
        password={$this->password}
        host={$this->host}
        port={$this->port}
        CNF;

        file_put_contents($tempCnf, $configg);
    
        $command = sprintf(
            'mysqldump --defaults-extra-file=%s %s 2>&1 > %s',
            escapeshellarg($tempCnf),
            escapeshellarg($this->db_name),
            escapeshellarg($absolutePath)
        );

       // Run command
        try {
        exec($command, $output, $exitCode);
        unlink($tempCnf); // Always clean up, delete temp file
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

        return $absolutePath;

        } catch (BackupFailedException $e) {
            // rethrow for upper-level service to handle
            throw $e;
        }
    }

    public function backupViaProfile(array $profile,string $absolutePath)
    {
        $command = sprintf(
            'mysqldump --user=%s --password=%s --host=%s --port=%s %s 2>&1 > %s',
            escapeshellarg($profile['username']),
            escapeshellarg($profile['password']),
            escapeshellarg($profile['host']),
            escapeshellarg($profile['port'] ?? '3306'),
            escapeshellarg($profile['database']),
            escapeshellarg($absolutePath)
        );

        $output = [];
        $result = 0;
        try{
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
            return $absolutePath;
        } catch (BackupFailedException $e) {
            throw $e;
        }
    }

    public function restore($filePath)
    {
        // Check if backup file exists
        if (!file_exists($filePath)) {
            throw new RestoreFailedException("Backup file not found at: $filePath", 'file_not_found');
        }
        
        $tempCnf = tempnam(sys_get_temp_dir(), 'mycnf_');

        file_put_contents($tempCnf, "[client]
            user={$this->username}
            password=\"{$this->password}\"
            host={$this->host}
            port={$this->port}");

        $command = sprintf(
            'mysql --defaults-extra-file=%s %s < %s 2>&1',
            escapeshellarg($tempCnf),
            escapeshellarg($this->db_name),
            escapeshellarg($filePath)
        );

        exec($command . ' 2>&1', $output, $exitCode);
        unlink($tempCnf); // Always clean up, delete temp file
        $outputText = implode("\n", $output);

        if ($exitCode !== 0) {
            // Check for common errors and throw typed exceptions if you want
            if (stripos($outputText, 'access denied') !== false) {
                throw new RestoreFailedException('Invalid database credentials.', 'invalid_credentials');
            }
            if (stripos($outputText, 'permission denied') !== false) {
                throw new RestoreFailedException('Permission denied while restoring database.', 'permission_denied');
            }
            if (stripos($outputText, 'unknown mysql server host') !== false ||
                stripos($outputText, "can't connect to mysql server") !== false) {
                throw new RestoreFailedException('Cannot connect to MySQL server.', 'host_unreachable');
            }

            // Unknown error fallback
            throw new RestoreFailedException("Restore failed: $outputText", 'unknown');
        }

        return true;
    }

    public function testConnection(): bool
    {
        $cmd = sprintf(
            'mysql -u%s -p%s -h%s -P%s -e "USE %s;"',
            escapeshellarg($this->username),
            escapeshellarg($this->password),
            escapeshellarg($this->host),
            $this->connection->port ?? 3306,
            escapeshellarg($this->db_name)
        );

        exec($cmd . ' 2>&1', $output, $exitCode);

        if ($exitCode !== 0) {
            throw new DatabaseConnectionException(implode("\n", $output)); // this is key
        }

        return true;
    }
}