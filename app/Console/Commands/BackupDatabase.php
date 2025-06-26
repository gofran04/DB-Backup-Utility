<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ConfigService;
use App\Services\DatabaseBackupService;
use App\Models\DatabaseConnection;
use App\Exceptions\BackupFailedException;

class BackupDatabase extends Command
{
    // protected $signature = 'db:backup {client_id}';
    protected $signature = 'db:backup {id?} {--profile=}';
    protected $description = 'Backup The Database';

    protected DatabaseBackupService $backupService;
    protected ConfigService $configService;

    public function __construct(DatabaseBackupService $backupService, ConfigService $configService)
    {
        parent::__construct();
        $this->backupService = $backupService;
        $this->configService = $configService;
    }


    public function handle()
    {
        $id = $this->argument('id');
        $profileName = $this->option('profile');
        $outputPath = 'backups'; // Directory where backups will be stored

        // Ensure one of the options is provided
        if (!$id && !$profileName) {
            $this->error('You must provide either a database ID or a --profile.');
            return Command::INVALID;
        }

        try {
            $dbConfig = null;

            if ($profileName) // Handle profile-based backup
            {
                $this->info("🔍 Loading DB config from profile: $profileName");
                $profiles = $this->configService->loadProfiles();

                if (!isset($profiles[$profileName])) {
                    $this->error("❌ Profile '$profileName' not found.");
                    return Command::FAILURE;
                }

                $dbConfig = $profiles[$profileName];
                $result = $this->backupService->backupUsingProfile($dbConfig, $outputPath);
            }
            else // Handle ID-based backup
            {
                $this->info("🔍 Loading DB config from database_connections table (ID: $id)");
                $connection = DatabaseConnection::find($id);

                if (!$connection) {
                    $this->error("❌ No database connection found with ID: $id");
                    return Command::FAILURE;
                }

                $result = $this->backupService->backup($id, $outputPath);
            }

            // Handle success response
            $this->info("✅ Backup successful!");
            $this->line("📁 File Path: storage/app/{$result['file_path']}");
            $this->line("📦 Size: {$result['file_size']} bytes");

            return Command::SUCCESS;

        } catch (BackupFailedException $e) {
            // Handle custom backup exceptions
            $this->error("❌ Backup failed: {$e->getMessage()} ({$e->getType()})");
            return Command::FAILURE;

        } catch (\Exception $e) {
            // Handle unexpected errors
            $this->error("💥 Unexpected error: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
//     public function handle(DatabaseBackupService $backupService)
//     {
//         $dbId = $this->argument('client_id');

//         // Get DB config for that client from your main DB
//         $conn = DB::table('database_connections')->find($dbId);
//         if (! $conn) {
//             $this->error("DB not found");
//             return 1;
//         }

//         // Create a temporary connection config
//         $connectionName = 'temp_' . uniqid();

//         Config::set("database.connections.{$connectionName}", [
//             'driver' => 'mysql',
//             'host' => $conn->host,
//             'port' => $conn->port,
//             'database' => $conn->db_name,
//             'username' => $conn->username,
//             'password' => $conn->password,
//             'charset' => 'utf8mb4',
//             'collation' => 'utf8mb4_unicode_ci',
//         ]);

//         DB::purge($connectionName);
//         DB::reconnect($connectionName);

//         $this->info("🔗 Connected. Creating backup...");

//         // Call the backup service
//         $result = $backupService->backup($connectionName, '/app/backups');

//         if ($result['status']) {
//             $this->info("Backup complete: " . $result['file']);
//         } else {
//             $this->error("Backup failed:\n" . $result['error']);
//         }

//         return 0;
//     }
// }
