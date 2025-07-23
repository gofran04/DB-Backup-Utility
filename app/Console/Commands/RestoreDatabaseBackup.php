<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ConfigService;
use Illuminate\Support\Facades\File;
use App\Models\DatabaseConnection;
use Illuminate\Support\Facades\Crypt;


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

        // ✅ Clean file path resolution (absolute, base_path, storage/app)
        if (file_exists($file)) {
            $resolvedPath = $file;
        } elseif (file_exists(base_path($file))) {
            $resolvedPath = base_path($file);
        } elseif (file_exists(storage_path("app/{$file}"))) {
            $resolvedPath = storage_path("app/{$file}");
        } else {
            $this->error("❌ Backup file not found: $file");
            return Command::FAILURE;
        }

        $file = $resolvedPath;

        $config = null;

        if ($profile) 
        {
            $this->info("🔍 Loading DB config from profile: $profile");
            $profiles = $this->configService->loadProfiles();

            if (!isset($profiles[$profile])) {
                $this->error("❌ Profile '$profile' not found.");
                return Command::FAILURE;
            }

            $config = $profiles[$profile];
        }

        if ($id) 
        {
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

        if(($config['driver'] == 'postgres') || ($config['driver'] == 'postgresql'))
            $config['driver'] = 'pgsql'; // laravel expect only 'pgsql'

        if (!empty($config['password'])) {
                try {
                    $config['password'] = Crypt::decryptString($config['password']);
                } catch (\Exception $e) {
                    $this->error("❌ Failed to decrypt password in profile: " . $e->getMessage());
                    return Command::FAILURE;
                }
            }
        $this->info("🔧 Starting restore operation for database: {$config['database']}");

        // ✅ Build restore command
        $command = null;
        switch ($config['driver']) {
            case 'mysql':
                $tempCnf = tempnam(sys_get_temp_dir(), 'mycnf');
                $configContent = <<<EOF
                [client]
                user={$config['username']}
                password="{$config['password']}"
                host={$config['host']}
                port={$config['port']}
                EOF;

                file_put_contents($tempCnf, $configContent);

                $command = sprintf(
                    'mysql --defaults-extra-file=%s %s < %s > /dev/null 2>&1',
                    escapeshellarg($tempCnf),
                    escapeshellarg($config['database']),
                    escapeshellarg($file)
                );
                break;

            case 'pgsql':
                putenv("PGPASSWORD={$config['password']}"); // hide password from CLI
                $command = sprintf(
                    'psql -h %s -p %s -U %s -d %s -f %s > /dev/null 2>&1',
                    escapeshellarg($config['host']),
                    escapeshellarg($config['port']),
                    escapeshellarg($config['username']),
                    escapeshellarg($config['database']),
                    escapeshellarg($file)
                );
                break;

            default:
                $this->error("❌ Unsupported driver: {$config['driver']}");
                return Command::FAILURE;
        }

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
