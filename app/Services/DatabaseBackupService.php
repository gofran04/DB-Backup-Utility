<?php
namespace App\Services;

use App\Exceptions\BackupFailedException;
use App\Exceptions\DatabaseConnectionException;
use App\Exceptions\CompresionFailedException;
use App\Services\TestDatabaseConnectionService;
use App\Models\DatabaseConnection;
use App\Services\Contracts\DatabaseAdapterInterface;
use Illuminate\Support\Facades\Log;
use App\Services\Compression\CompressionServiceInterface;

class DatabaseBackupService
{

    protected DatabaseAdapterInterface $adapter;

    public function __construct(DatabaseAdapterInterface $adapter, protected CompressionServiceInterface $compressor)
    {
        $this->adapter = $adapter;
    }

    public function backup(DatabaseConnection $connection, string $outputPath): array
    {
        try {
            $this->adapter->testConnection();
        } catch (\Exception $e) {
            $message = $e->getMessage();

            if (str_contains($message, 'Access denied')) {
                throw new DatabaseConnectionException('Invalid database credentials.', 'invalid_credentials');
            }
            if (str_contains($message, 'Unknown database')) {
                throw new DatabaseConnectionException('Database does not exist.', 'database_missing');
            }
            if (str_contains($message, 'Connection refused') || str_contains($message, 'php_network_getaddresses')) {
                throw new DatabaseConnectionException('Database host unreachable.', 'host_unreachable');
            }
            if (str_contains($message, 'timed out')) {
                throw new DatabaseConnectionException('Connection timed out.', 'connection_timeout');
            }
        }

        // Generate filename and paths
        $connectionName = 'temp_' . uniqid();
        $filename = $connection->db_name.'_'.$connectionName . '_backup_' . now()->format('Ymd_His') . '.sql';

        $relativePath = trim($outputPath, '/') . '/' . $filename;
        $absolutePath = storage_path('app/' . $relativePath);

        $sqlFile = $this->adapter->backup($absolutePath);
        $sqlFile = $sqlFile.'ss';
/////

        try { // compress dump file
            $gzFile = $this->compressor->compress($sqlFile, level: 6);
            Log::info("Backup compressed: {$gzFile}");
        } catch (CompresionFailedException $e) {
            Log::error("Compression failed: " . $e->getMessage());
            throw $e;

        }
/////
        $fileSize = file_exists($absolutePath) ? filesize($absolutePath) : null;

        return [
            'relative_path' => $relativePath,
            'absolute_path' => $absolutePath,
            'file_size'     => $fileSize,
        ];
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
