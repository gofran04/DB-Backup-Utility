<?php

namespace Tests\Feature\ConsoleTests;

use Tests\TestCase;
use App\Models\DatabaseConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use App\Factories\DatabaseAdapterFactory;
use App\Services\Compression\CompressionServiceInterface;
use App\Services\DatabaseBackupService;
use App\Services\ConfigService;
use Illuminate\Support\Facades\Crypt;

class RestoreDatabaseCommandTest extends TestCase
{
    use RefreshDatabase;

    protected string $storagePath;
    protected string $testConfigDir;
    protected string $testConfigPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Set test-specific backup path
        $this->storagePath = storage_path('app/test-backups');

        // Create the directory if it doesn't exist
        if (!File::exists($this->storagePath)) {
            File::makeDirectory($this->storagePath, 0777, true, true);
        }

        // Use a temp config path to avoid messing with real user config
        $this->testConfigDir = base_path('tests/temp-config');
        $this->testConfigPath = $this->testConfigDir . '/config.json';

        // Ensure directory exists and create empty config file
        if (!File::exists($this->testConfigDir)) {
            File::makeDirectory($this->testConfigDir, 0755, true);
        }
        File::put($this->testConfigPath, json_encode(['profiles' => []], JSON_PRETTY_PRINT));
    }

    protected function tearDown(): void
    {
        // Clean up the test-backups folder
        if (File::exists($this->storagePath)) {
            File::cleanDirectory($this->storagePath);
        }
        // Clean up temp config after test
        if (File::exists($this->testConfigPath)) {
            File::delete($this->testConfigPath);
        }
        if (File::exists($this->testConfigDir)) {
            File::deleteDirectory($this->testConfigDir);
        }

        parent::tearDown();
    }

    public function test_restore_db_via_command_successfully_using_db_id()
    {
        $result = $this->createBackupForRestoreTest();

        $dbConnection = $result['db_connection'];
        $backupJob = $result['backup_job'];
        $fullPath = $result['full_path'];

        $this->artisan('backup:restore',[
            'file' => $backupJob['relative_path'],
            '--id' => $dbConnection->id,
            ])
            ->expectsOutput("🔍 Loading DB config from database_connections table (ID: $dbConnection->id)")
            ->expectsOutput("🚀 Starting restore...")
            ->expectsOutput("✅ Restore complete.")
            ->assertExitCode(0);

        $this->assertFileExists($fullPath);
    }

    public function test_restore_db_via_command_successfully_using_profile()
    {
        $configService = $this->app->make(ConfigService::class);

        // Save a dummy profile first
        $configService->saveProfiles([
            'temp_profile' => [
                'driver'   => 'mysql',
                'host'     => '127.0.0.1',
                'port'     => 3306,
                'database' => 'testing_db',
                'username' => 'newuser',
                'password' => Crypt::encryptString(env('DB_PASSWORD')), 
            ],
        ]);

        $result = $this->createBackupForRestoreTest();
        
        $dbConnection = $result['db_connection'];
        $backupJob = $result['backup_job'];
        $fullPath = $result['full_path'];        

        $this->artisan('backup:restore',[
            'file'      => $backupJob['relative_path'],
            '--profile' => 'temp_profile',
            ])
            ->expectsOutput("🔍 Loading DB config from profile: temp_profile")
            ->expectsOutput("🚀 Starting restore...")
            ->expectsOutput("✅ Restore complete.")
            ->assertExitCode(0);

        $this->assertFileExists($fullPath);
    }

    public function test_restore_db_via_command_fails_when_both_db_id_and_profile_provided()
    {
        $result = $this->createBackupForRestoreTest();

        $dbConnection = $result['db_connection'];
        $backupJob = $result['backup_job'];
        $fullPath = $result['full_path'];

        $this->artisan('backup:restore',[
            'file'      => $backupJob['relative_path'],
            '--id'      => $dbConnection->id,
            '--profile' => 'temp_profile',// not nescessery provide existed profile. it will fails any way
            ])
            ->expectsOutput('❌ You must provide either --id OR --profile (but not both).')
            ->assertExitCode(2);//Command::INVALID return === 2
    }

    public function test_restore_db_via_command_fails_when_neither_db_id_or_profile_provided()
    {
        $result = $this->createBackupForRestoreTest();

        $dbConnection = $result['db_connection'];
        $backupJob = $result['backup_job'];
        $fullPath = $result['full_path'];

        $this->artisan('backup:restore',[
            'file'      => $backupJob['relative_path'],
            ])
            ->expectsOutput('❌ You must provide either --id OR --profile (but not both).')
            ->assertExitCode(2);//Command::INVALID return === 2
    }

    public function test_restore_db_via_command_fails_when_the_dump_file_does_not_exist()
    {
        $db_connection = DatabaseConnection::factory()->create();

        $this->artisan('backup:restore',[
            'file'      => 'not_existed_file.sql',
            '--id'      => $db_connection->id,
            ])
            ->expectsOutput("❌ Backup file not found: not_existed_file.sql")
            ->assertExitCode(1);//failure
    }

    public function test_restore_db_via_command_successfully_by_providing_compressed_dump_file()
    {
        $result = $this->createBackupForRestoreTest();

        $dbConnection = $result['db_connection'];
        $backupJob = $result['backup_job'];
        $fullPath = $result['full_path'];

        $this->artisan('backup:restore',[
            'file' => $backupJob['relative_path'].'.gz',
            '--id' => $dbConnection->id,
            ])
            ->expectsOutput("🔄 Decompressing file...")
            ->expectsOutput("🔍 Loading DB config from database_connections table (ID: $dbConnection->id)")
            ->expectsOutput("🚀 Starting restore...")
            ->expectsOutput("✅ Restore complete.")
            ->assertExitCode(0);

        $this->assertFileExists($fullPath.'.gz');
    }

    private function createBackupForRestoreTest(string $dbName = 'testing_db'): array
    {
        $dbConnection = DatabaseConnection::factory()->create([
            'db_name' => $dbName,
        ]);

        $compressor = app(CompressionServiceInterface::class);
        $adapter = app(DatabaseAdapterFactory::class)->make($dbConnection);
        $adapter->createDatabaseIfNotExists();

        $backupService = new DatabaseBackupService($adapter, $compressor);
        $backupJob = $backupService->backupUsingDbId($dbConnection, $this->storagePath);

        return [
            'db_connection' => $dbConnection,
            'backup_job' => $backupJob,
            'full_path' => storage_path('app/' . $backupJob['relative_path']),
        ];
    }
}