<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Installer\EnvironmentCheckService;
use App\Services\ConfigService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use App\Models\DatabaseConnection;
use Illuminate\Support\Facades\Crypt;


class InstallBackupUtility extends Command
{

    protected $signature = 'program:install';
    protected $description = 'Setup environment, migrate, and add initial backup database connection';

    public function handle()
    {
        //Step 1 — Welcome & Project Info

        $this->info('🚀 Welcome to DB Backup Utility Installer!
This will set up your database connections, create tables, and configure defaults.And prepare the ground to run backup/restore DBs operations');
   
        //Step 2 — Environment Checks

        $envChecker = app(EnvironmentCheckService::class);

        $checks = [
            $envChecker->checkPhpVersion(),
            $envChecker->checkMySqlAvailability(),
            $envChecker->checkPostgresAvailability(),
            $envChecker->checkStorageWritable(),
        ];

        foreach ($checks as $check) {
            $this->line($check['message']);
            if (!$check['status']) {
                $this->warn("⚠️ Some requirements are missing. Please fix them before proceeding.");
            return Command::FAILURE;
            }
        }
        
        //Step 3 — Run Migrations

        // Check main DB connection (.env)
        $this->info("Checking main app DB connection...");
        try {
            DB::connection()->getPdo();
            $this->info("✅ Main app DB connection successful.");
        } catch (\Exception $e) {
            $this->error("❌ Cannot connect to main app DB. Please check your .env settings.");
            $this->error("Error: " . $e->getMessage());
            return Command::FAILURE;
        }

        $this->info("Running migrations...");
        Artisan::call('migrate', ['--force' => true]);
        $this->info(Artisan::output());

        //Step 4 — Config.json Setup And Prompt user to create first backup DB connection

         // 3. Use ConfigService to ensure config.json exists
        /** @var ConfigService $configService */
        $configService = app(ConfigService::class);
        $configService->ensureConfigFileExists();

         if ($this->confirm('Do you want to create your first backup database connection now?')) {
            $dbType = $this->choice('Database type', ['mysql', 'pgsql'], 0);
            $host = $this->ask('Database host', '127.0.0.1');
            $port = $this->ask('Database port', $dbType === 'mysql' ? 3306 : 5432);
            $username = $this->ask('Database username');
            $password = $this->secret('Database password');
            $database = $this->ask('Database name');

            $storeOption = $this->choice('Where to store this connection?', ['Database', 'Config file', 'Both'], 0);

            if (in_array($storeOption, ['Config file', 'Both'])) {
                $profileName = $this->ask('Profile name', 'default_profile');

                // Load existing profiles and add new one
                $profiles = $configService->loadProfiles();
                $shouldSaveProfile = true;

                // check config.json if there is a profile with the same name 
                if (isset($profiles[$profileName])) 
                {
                    if (!$this->confirm("Profile '$profileName' already exists. Overwrite?", false)) {
                        $this->warn("Skipped saving profile '$profileName' to config.json.");
                        $shouldSaveProfile = false;
                    }
                }
                
                if($shouldSaveProfile)
                {
                    $profiles[$profileName] = [
                        'driver'   => $dbType,
                        'host'     => $host,
                        'port'     => (int)$port,
                        'username' => $username,
                        'password' => Crypt::encryptString($password),
                        'database' => $database,
                    ];
                    $configService->saveProfiles($profiles);

                    $this->info("✅ Profile '{$profileName}' added to config.json at {$configService->getConfigPath()}");
                }
            }

            if (in_array($storeOption, ['Database', 'Both'])) {
                // Store in DB using your DatabaseConnection model
                DatabaseConnection::create([
                    'connection_name' => $this->ask('Database connection name', 'default_connection'),
                    'type'            => $dbType,
                    'host'            => $host,
                    'port'            => (int)$port,
                    'username'        => $username,
                    'password'        => Crypt::encryptString($password),
                    'db_name'         => $database,
                ]);
                $this->info("✅ Database connection stored in main DB.");
            }
        }

        $this->info("🎉 Installation complete! You can now run backups via CLI or API.");
        $this->info("Next step: use `php artisan backup:quickstart` to schedule backups and run your first backup.");

        return Command::SUCCESS;
    }
}
