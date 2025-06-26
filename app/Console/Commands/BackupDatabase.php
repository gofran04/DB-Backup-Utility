<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ConfigService;
use App\Services\DatabaseBackupService;
use App\Models\DatabaseConnection;
use App\Exceptions\BackupFailedException;
use Illuminate\Support\Facades\Log;

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
            $logContext = [
                'source' => 'CLI',
                'invoked_at' => now()->toDateTimeString()
            ];

            $dbConfig = null;

            if ($profileName) // Handle profile-based backup
            {
                $logContext['profile'] = $profileName;
                Log::info("🔧 CLI Backup Started (Profile)", $logContext);

                $this->info("🔍 Loading DB config from profile: $profileName");
                $profiles = $this->configService->loadProfiles();

                if (!isset($profiles[$profileName])) {
                    Log::error("❌ CLI Backup Failed: Profile not found", $logContext);
                    $this->error("❌ Profile '$profileName' not found.");
                    return Command::FAILURE;
                }

                $dbConfig = $profiles[$profileName];
                $result = $this->backupService->backupUsingProfile($dbConfig, $outputPath);
            }
            else // Handle ID-based backup
            {
                // Log at the start of backup
                $logContext['db_id'] = $id;
                Log::info("🔧 CLI Backup Started (DB ID)", $logContext);

                $this->info("🔍 Loading DB config from database_connections table (ID: $id)");
                $connection = DatabaseConnection::find($id);

                if (!$connection) {
                    Log::error("❌ CLI Backup Failed: DB ID not found", $logContext);
                    $this->error("❌ No database connection found with ID: $id");
                    return Command::FAILURE;
                }

                $result = $this->backupService->backup($id, $outputPath);
            }

            $duration = now()->diffInSeconds($logContext['invoked_at']);

            // Handle success response
            $this->info("✅ Backup successful!");
            $this->line("📁 File Path: storage/app/{$result['file_path']}");
            $this->line("📦 Size: {$result['file_size']} bytes");

            //log after backup operation success
            Log::info("✅ CLI Backup Success", array_merge($logContext, [
                'file_path' => $result['file_path'],
                'file_size' => $result['file_size'],
                'duration'  => $duration,
            ]));

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