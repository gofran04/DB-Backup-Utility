<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ConfigService;
use App\Models\DatabaseConnection;
use App\Exceptions\BackupFailedException;
use Illuminate\Support\Facades\Log;
use App\Models\BackupJob;
use App\Jobs\ProcessDatabaseBackup;

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
        $outputPath = config('backup.storage_path') . '/backups'; // Directory where backups will be stored

        // Ensure one of the options is provided
        if (!$id && !$profileName) {
            $this->error('You must provide either a database ID or a --profile.');
            return Command::INVALID;
        }elseif($id && $profileName) {
            $this->error('You must provide a database ID or a --profile. Not both');
            return Command::INVALID;
        }

        try {
            $logContext = [
                'source' => 'CLI',
                'invoked_at' => now()->toDateTimeString()
            ];
            $db_name = null;

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
                     
                $db_name = $profiles[$profileName]['database'];

                // Create backup job entry in DB
                $backupJob = BackupJob::create([
                    'profile_name' => $profileName,
                    'status'       => 'pending',
                    'mechanism'    => 'manual',
                    'started_at'   => now()
                ]);

            }else{ // Handle ID-based backup
                $logContext['db_id'] = $id; // Log at the start of backup
                Log::info("🔧 CLI Backup Started (DB ID)", $logContext);

                $this->info("🔍 Loading DB config from database_connections table (ID: $id)");
                $connection = DatabaseConnection::find($id);

                if (!$connection) {
                    Log::error("❌ CLI Backup Failed: DB ID not found", $logContext);
                    $this->error("❌ No database connection found with ID: $id");
                    return Command::FAILURE;
                }

                $db_name = $connection->db_name;

                // Create backup job entry in DB
                $backupJob = BackupJob::create([
                    'database_connection_id' => $id,
                    'status'                 => 'pending',
                    'mechanism'              => 'manual',
                    'started_at'             => now()
                ]);
            }

            Log::info("📦 Dispatching backup job (CLI)", array_merge($logContext, [
                'database_name' => $db_name,
                'backup_job'    => $backupJob->id
            ]));

            // Dispatch the queued job
            ProcessDatabaseBackup::dispatch($backupJob->id)->onQueue('backups');

            $this->info("✅ Backup job queued successfully (Job ID: {$backupJob->id})");
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