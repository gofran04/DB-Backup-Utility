<?php
namespace App\Services;

use App\Services\ConfigService;
use App\Models\DatabaseConnection;
use Symfony\Component\HttpFoundation\Response;

class RestoreBackupService
{
    protected ConfigService $configService;

    public function __construct(ConfigService $configService)
    {
        $this->configService = $configService;
    }

    public function restore($validated) 
    {
        $file = $validated['file'];

        // ✅ Normalize file path
        if (!str_starts_with($file, '/') && !preg_match('/^[A-Z]:\\\\/', $file)) {
            $file = base_path($file);
        }

        if (!file_exists($file)) {
            return [
                'status' => 404,
                'data' => ['message' => "Backup file not found: $file"],
            ];
        }

        $config = null;

        if (! empty($validated['db_profile'])) {  // Load DB  from config file (via profile)
            $profile = $validated['db_profile'];
            $profiles = $this->configService->loadProfiles();

            if (!isset($profiles[$profile])) {
                return [
                    'status' => 404,
                    'data' => ['message' => "Profile '$profile' not found."],
                ];
            }

            $config = $profiles[$profile];

        }elseif ($validated['db_id']) { // Load DB  from Database (via id)
            $db_id = $validated['db_id'];
            $connection = DatabaseConnection::find($db_id);

            if (!$connection) {
                return [
                    'status' => 404,
                    'data' => ['message' => "Database ID '$db_id' not found."],
                ];
            }

            $config = [
                'driver'   => $connection->type,
                'host'     => $connection->host,
                'port'     => $connection->port,
                'database' => $connection->db_name,
                'username' => $connection->username,
                'password' => $connection->password,
            ];
        }

        // ✅ Validate driver
        if (!isset($config['driver']) || $config['driver'] !== 'mysql') {
            return [
                'status' => 422,
                'data' => ['message' => 'Only MySQL is supported for restore at this time.'],
            ];
        }

        // ✅ Build restore command
        $command = sprintf(
            'mysql -h%s -P%s -u%s -p%s %s < %s',
            escapeshellarg($config['host']),
            escapeshellarg($config['port'] ?? '3306'),
            escapeshellarg($config['username']),
            escapeshellarg($config['password']),
            escapeshellarg($config['database']),
            escapeshellarg($file)
        );

        $exitCode = null;
        system($command, $exitCode);

        if ($exitCode === 0) {
            return [
                'status' => 200,
                'data' => ['message' => 'Database restored successfully.'],
            ];
        }

        return [
            'status' => 500,
            'data' => ['message' => "Restore failed with exit code: $exitCode"],
        ];        
    }
}