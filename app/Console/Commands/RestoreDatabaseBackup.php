<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ConfigService;
use Illuminate\Support\Facades\File;
use App\Models\DatabaseConnection;

class RestoreDatabaseBackup extends Command
{
    protected $signature = 'backup:restore {file} {--id=} {--profile=}';
    protected $description = 'Restore a database from a backup file';

    protected ConfigService $configService;

    public function __construct(ConfigService $configService)
    {
        parent::__construct();
        $this->configService = $configService;
    }

    public function handle()
    {
        $file     = $this->argument('file');
        $id       = $this->option('id');
        $profile  = $this->option('profile');

        // ✅ Enforce: only one of --id OR --profile must be used
        if (($id && $profile) || (!$id && !$profile)) {
            $this->error('❌ You must provide either --id OR --profile (but not both).');
            return Command::FAILURE;
        }

        // ✅ Normalize file path
        if (!str_starts_with($file, '/') && !preg_match('/^[A-Z]:\\\\/', $file)) {
            $file = base_path($file);
        }

        if (!file_exists($file)) {
            $this->error("❌ Backup file not found: $file");
            return Command::FAILURE;
        }

        $config = null;

        if ($profile) {  // Load DB  from config file (via profile)
            $this->info("🔍 Loading DB config from profile: $profile");
            $profiles = $this->configService->loadProfiles();

            if (!isset($profiles[$profile])) {
                $this->error("❌ Profile '$profile' not found.");
                return Command::FAILURE;
            }

            $config = $profiles[$profile];
        }

        if ($id) { // Load DB  from Database (via id)
            $this->info("🔍 Loading DB config from database_connections table (ID: $id)");
            $connection = DatabaseConnection::find($id);

            if (!$connection) {
                $this->error("❌ No database connection found with ID: $id");
                return Command::FAILURE;
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
            $this->error("❌ Only MySQL driver is supported for restore at this time.");
            return Command::FAILURE;
        }

        $this->info("🔧 Starting restore operation for database: {$config['database']}");

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

        $this->info("🚀 Running restore command...");
        $exitCode = null;
        system($command, $exitCode);

        if ($exitCode === 0) {
            $this->info("✅ Database restored successfully.");
            return Command::SUCCESS;
        }

        $this->error("❌ Restore failed with exit code: $exitCode");
        return Command::FAILURE;
    }


}
