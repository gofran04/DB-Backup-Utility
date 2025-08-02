<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\DatabaseConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use App\Services\ConfigService;
use Illuminate\Support\Facades\Crypt;

class BackupDatabaseCommandTest extends TestCase
{
    use RefreshDatabase;
    protected string $testConfigDir;
    protected string $testConfigPath;


    protected function setUp():void
    {
        parent::setUp();
  
        $testBackupPath = storage_path('app/test-backups');
        File::ensureDirectoryExists($testBackupPath);
        Config::set('backup.storage_path', $testBackupPath);

        // Use a temp config path to avoid messing with real user config
        $this->testConfigDir = base_path('tests/temp-config');
        $this->testConfigPath = $this->testConfigDir . '/config.json';

        // Ensure directory exists and create empty config file
        if (!File::exists($this->testConfigDir)) {
            File::makeDirectory($this->testConfigDir, 0755, true);
        }
        File::put($this->testConfigPath, json_encode(['profiles' => []], JSON_PRETTY_PRINT));

        // Mock ConfigService to use our test config path
        $this->app->bind(ConfigService::class, function () {
            $mock = new class($this->testConfigDir, $this->testConfigPath) extends \App\Services\ConfigService {
                public function __construct($dir, $path)
                {
                    $this->configDir = $dir;
                    $this->configPath = $path;
                }
            };
            return $mock;
        });
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('app/test-backups'));
        // Clean up temp config after test
        if (File::exists($this->testConfigPath)) {
            File::delete($this->testConfigPath);
        }
        if (File::exists($this->testConfigDir)) {
            File::deleteDirectory($this->testConfigDir);
        }
        parent::tearDown();
    }

    public function test_backup_db_via_command_successfully_using_db_id()
    {
        $db_connection = DatabaseConnection::factory()->create();

        $this->artisan('db:backup',['id' => $db_connection->id])
            ->expectsOutput("🔍 Loading DB config from database_connections table (ID: $db_connection->id)")
            ->expectsOutput("✅ Backup successful!")
            ->assertExitCode(0);
    }

    public function test_backup_db_via_command_fails_when_using_not_found_db_id()
    {
        $this->artisan('db:backup',['id' => 9999]) // using not found db
            ->expectsOutput("🔍 Loading DB config from database_connections table (ID: 9999)")
            ->expectsOutput("❌ No database connection found with ID: 9999")
            ->assertExitCode(1);//failure
    }

    public function test_backup_db_via_command_successfully_using_profile()
    {
        $configService = $this->app->make(ConfigService::class);

        // Save a dummy profile first
        $configService->saveProfiles([
            'temp_profile' => [
                'driver'   => 'mysql',
                'host'     => '127.0.0.1',
                'port'     => 3306,
                'database' => 'TechFlex',
                'username' => 'newuser',
                'password' => Crypt::encryptString(env('TEST_DB_PASSWORD')), 
            ],
        ]);
        $this->artisan('db:backup',['--profile' => 'temp_profile'])
            ->expectsOutput("🔍 Loading DB config from profile: temp_profile")
            ->expectsOutput("✅ Backup successful!")
            ->assertExitCode(0);
    }

    public function test_backup_db_via_command_fails_when_using_not_found_profile()
    {
        $this->artisan('db:backup',['--profile' => 'not_founded_profile'])
            ->expectsOutput("❌ Profile 'not_founded_profile' not found.")
            ->assertExitCode(1);//failure
    }

    public function test_backup_db_via_command_fails_when_neither_db_id_or_profile_provided()
    {
        $this->artisan('db:backup')
            ->expectsOutput('You must provide either a database ID or a --profile.')
            ->assertExitCode(2);//Command::INVALID return === 2
    }

    public function test_backup_db_via_command_fails_when_both_db_id_and_profile_provided()
    {
        $this->artisan('db:backup',['id' => 1 ,'--profile' => 'temp_profile'])
            ->expectsOutput('You must provide a database ID or a --profile. Not both')
            ->assertExitCode(2);//Command::INVALID return === 2
    }

    public function test_backup_db_via_command_success_and_dump_file_exist()
    {
        $db_connection = DatabaseConnection::factory()->create();

        $this->artisan('db:backup',['id' => $db_connection->id])
            ->expectsOutput("🔍 Loading DB config from database_connections table (ID: $db_connection->id)")
            ->expectsOutput("✅ Backup successful!")
            ->assertExitCode(0);

        // Compose expected backup file path (adjust if your backup filename format is different)
        $backupDir = storage_path('app/test-backups/backups');
        
        // Since you may not know exact filename, check that backup dir is not empty
        $files = File::files($backupDir);

        // Assert backup directory exists and has at least one file
        $this->assertTrue(File::exists($backupDir));
        $this->assertNotEmpty($files, "No backup file was created in $backupDir");
    }
}