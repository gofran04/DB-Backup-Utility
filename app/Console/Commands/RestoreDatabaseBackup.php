<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ConfigService;
use App\Models\DatabaseConnection;
use App\Factories\DatabaseAdapterFactory;
use App\Services\RestoreBackupService;
use App\Services\Compression\DecompressionServiceInterface;
use App\Exceptions\RestoreFailedException;

class RestoreDatabaseBackup extends Command
{
    protected $signature = 'backup:restore {file} {--id=} {--profile=}';
    protected $description = 'Restore a database from a backup file';

    protected ConfigService $configService;
    protected DecompressionServiceInterface $decompressor;

    public function __construct(ConfigService $configService,DecompressionServiceInterface $decompressor)
    {
        parent::__construct();
        $this->configService = $configService;
        $this->decompressor = $decompressor;
    }

    public function handle()
    {
        $file    = $this->argument('file');
        $id      = $this->option('id');
        $profile = $this->option('profile');

        // ✅ Enforce only one input method
        if (($id && $profile) || (!$id && !$profile)) {
            $this->error('❌ You must provide either --id OR --profile (but not both).');
            return Command::INVALID;
        }

        // ✅ Resolve file path
        $resolvedPath = $this->resolveFilePath($file);
        if (!$resolvedPath) {
            $this->error("❌ Backup file not found: $file");
            return Command::FAILURE;
        }

        // ✅ Decompress if needed
        if (str_ends_with($resolvedPath, '.gz')) {
            try {
                $this->info("🔄 Decompressing file...");
                $resolvedPath = $this->decompressor->decompress($resolvedPath);
            } catch (RestoreFailedException $e) {
                $this->error("❌ Decompression failed: " . $e->getMessage());
                return Command::FAILURE;
            }
        }

        // ✅ Build adapter
        $adapterFactory = new DatabaseAdapterFactory();
        $adapter = null;
        $validated = ['file' => $resolvedPath];

        if ($id) {// use db_id
            $this->info("🔍 Loading DB config from database_connections table (ID: $id)");
            $connection = DatabaseConnection::find($id);
            if (!$connection) {
                $this->error("❌ No database connection found with ID: $id");
                return Command::FAILURE;
            }
            $adapter = $adapterFactory->make($connection);
        } else { //use profile
            $this->info("🔍 Loading DB config from profile: $profile");
            $profiles = $this->configService->loadProfiles();
            if (!isset($profiles[$profile])) {
                $this->error("❌ Profile '$profile' not found.");
                return Command::FAILURE;
            }
            $validated['db_profile'] = $profile;
            $adapter = $adapterFactory->makeFromProfile($profiles[$profile]);
        }

        // ✅ Call restore service
        $restoreService = new RestoreBackupService($this->configService, $adapter);

        try {
            $this->info("🚀 Starting restore...");
            $restoreService->restore($validated);
            $this->info("✅ Restore complete.");
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