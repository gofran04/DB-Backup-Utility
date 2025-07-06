<?php
namespace App\Services;

use App\Services\ConfigService;
use App\Exceptions\DatabaseConnectionException;
use App\Exceptions\BackupFailedException;
use App\Services\Contracts\DatabaseAdapterInterface;

class RestoreBackupService
{
    protected ConfigService $configService;
    protected DatabaseAdapterInterface $adapter;

    public function __construct(ConfigService $configService,DatabaseAdapterInterface $adapter)
    {
        $this->configService = $configService;
        $this->adapter = $adapter;
    }

    public function restore($validated)
    {
        try { // Test DB connection before restore
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

        $file = $validated['file'];

        if (!str_starts_with($file, '/') && !preg_match('/^[A-Z]:\\\\/', $file)) {
            $file = storage_path('app/' . $file);
        }

        if (!file_exists($file)) {
            throw new BackupFailedException("Backup file not found: $file", 'file_not_found');
        }

        // Run restore (delegated to adapter)
        $this->adapter->restore($file);

        return [
            'message' => 'Database restored successfully.',
        ];
    }
}