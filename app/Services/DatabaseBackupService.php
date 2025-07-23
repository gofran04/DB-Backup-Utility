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

    public function backupUsingDbId(DatabaseConnection $connection, string $outputPath): array
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

        $absolutePath = rtrim($outputPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;
        $relativePath = str_replace(storage_path('app/'), '', $absolutePath);

        $sqlFile = $this->adapter->backupViaDbId($absolutePath);  

        try { // compress dump file
            $gzFile = $this->compressor->compress($sqlFile, level: 6);
            Log::info("Backup compressed: {$gzFile}");
        } catch (CompresionFailedException $e) {
            Log::error("Compression failed: " . $e->getMessage());
            throw $e;
        }
        
        $fileSize = file_exists($absolutePath) ? filesize($absolutePath) : null;

        return [
            'relative_path' => $relativePath,
            'absolute_path' => $absolutePath,
            'file_size'     => $fileSize,
        ];
    }

    public function backupUsingProfile(array $profile,string $outputPath)
    {
        try {// First: test connection dynamically
            TestDatabaseConnectionService::testProfileConnection($profile); 
        } catch (\Exception $e) {
            throw $e;
        }
        
        // File name format
        $filename = "{$profile['database']}_backup_" . date('Ymd_His') . ".sql";

        $absolutePath = rtrim($outputPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;
        $relativePath = str_replace(storage_path('app/'), '', $absolutePath);

        $dumplFile = $this->adapter->backupViaProfile($absolutePath);
        
        try { // compress dump file
            $gzFile = $this->compressor->compress($dumplFile, level: 6);
            Log::info("Backup compressed: {$gzFile}");
        } catch (CompresionFailedException $e) {
            Log::error("Compression failed: " . $e->getMessage());
            throw $e;
        }
        
        $fileSize = file_exists($absolutePath) ? filesize($absolutePath) : null;

        return [
            'relative_path' => $relativePath,
            'absolute_path' => $absolutePath,
            'file_size'     => $fileSize,
        ];

    }
}
