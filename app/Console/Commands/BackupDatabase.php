<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ConfigService;
use App\Services\DatabaseBackupService;
use App\Models\DatabaseConnection;
use App\Exceptions\BackupFailedException;
use Illuminate\Support\Facades\Log;
use App\Factories\DatabaseAdapterFactory;
use App\Services\Compression\CompressionServiceInterface;

class BackupDatabase extends Command
{
    protected $signature = 'db:backup {id?} {--profile=}';
    protected $description = 'Backup The Database';

    protected ConfigService $configService;

    public function __construct( ConfigService $configService)
    {
        // NOTE: Cannot inject DatabaseBackupService in constructor because
        // it depends on a runtime-specific adapter (resolved after knowing the DB type).
        parent::__construct();
        $this->configService = $configService;
    }


    public function handle()
    {
        $id = $this->argument('id');
        $profileName = $this->option('profile');
        $outputPath = config('backup.storage_path') . 'backups'; // Directory where backups will be stored

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
                           
                $adapter = (new DatabaseAdapterFactory())->makeFromProfile($dbConfig);
                $compressor = app(CompressionServiceInterface::class);
                $backupService = new DatabaseBackupService($adapter, $compressor);

                $result = $backupService->backupUsingProfile($dbConfig, $outputPath); // assuming this method exists            
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

                $adapter = (new DatabaseAdapterFactory())->make($connection);
                $compressor = app(CompressionServiceInterface::class);
                $backupService = new DatabaseBackupService($adapter, $compressor);

                $result = $backupService->backupUsingDbId($connection, $outputPath);
            }

            $duration = now()->diffInSeconds($logContext['invoked_at']);

            // Handle success response
            $this->info("✅ Backup successful!");
            $this->line("📁 File Path: storage/app{$result['relative_path']}");
            $this->line("📦 Size: {$result['file_size']} bytes");

            //log after backup operation success
            Log::info("✅ CLI Backup Success", array_merge($logContext, [
                'file_path' => $result['relative_path'],
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