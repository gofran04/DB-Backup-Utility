<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ConfigService;
use App\Models\DatabaseConnection;
use Illuminate\Support\Facades\Log;
use App\Jobs\ProcessRestore;
use App\Exceptions\RestoreFailedException;

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
        $file    = $this->argument('file');
        $id      = $this->option('id');
        $profile = $this->option('profile');

        // ✅ Validate arguments (only one method allowed)
        if (($id && $profile) || (!$id && !$profile)) {
            $this->error('❌ You must provide either --id OR --profile (but not both).');
            return Command::INVALID;
        }

        // ✅ Validate file existence (using resolved path)
        if (!$this->resolveFilePath($file)) {
            $this->error("❌ Backup file not found: $file");
            return Command::FAILURE;
        }

        try {
            $logContext = [
                'source'     => 'CLI',
                'invoked_at' => now()->toDateTimeString(),
                'file'       => $file
            ];

            // ✅ Keep original relative path (like API does)
            $validated = ['file' => $file];

            if ($profile) {
                $logContext['profile'] = $profile;
                Log::info("🔧 CLI Restore Started (Profile)", $logContext);

                $this->info("🔍 Loading DB config from profile: $profile");
                $profiles = $this->configService->loadProfiles();

                if (!isset($profiles[$profile])) {
                    Log::error("❌ CLI Restore Failed: Profile not found", $logContext);
                    $this->error("❌ Profile '$profile' not found.");
                    return Command::FAILURE;
                }
                $validated['db_profile'] = $profile;

            } else {
                $logContext['db_id'] = $id;
                Log::info("🔧 CLI Restore Started (DB ID)", $logContext);

                $this->info("🔍 Loading DB config from database_connections table (ID: $id)");
                $connection = DatabaseConnection::find($id);

                if (!$connection) {
                    Log::error("❌ CLI Restore Failed: DB ID not found", $logContext);
                    $this->error("❌ No database connection found with ID: $id");
                    return Command::FAILURE;
                }
                $validated['db_id'] = $id;
            }

            Log::info("📦 Dispatching restore job (CLI)", $logContext);

            // ✅ Dispatch queued job
            ProcessRestore::dispatch($validated)->onQueue('restore');

            $this->info("✅ Restore job queued successfully");
            return Command::SUCCESS;

        } catch (RestoreFailedException $e) {
            $this->error("❌ Restore failed: {$e->getMessage()}");
            return Command::FAILURE;

        } catch (\Exception $e) {
            $this->error("💥 Unexpected error: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    protected function resolveFilePath(string $file): ?string
    {
        if (file_exists($file)) {
            return $file;
        } elseif (file_exists(base_path($file))) {
            return base_path($file);
        } elseif (file_exists(storage_path("app/{$file}"))) {
            return storage_path("app/{$file}");
        }
        return null;
    }
}
