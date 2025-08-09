<?php

namespace Tests\Feature\ConsoleExceptionTests;

use Tests\TestCase;
use App\Models\DatabaseConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use App\Exceptions\RestoreFailedException;
use App\Factories\DatabaseAdapterFactory;
use App\Services\Compression\CompressionServiceInterface;
use App\Services\RestoreBackupService;
use App\Services\DatabaseBackupService;
use Mockery;

class RestoreDatabaseCommandExceptionFailureTest extends TestCase
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

    public function test_restore_fails_and_exception_thrown_and_caught()
    {
        $mock = Mockery::mock('overload:' . RestoreBackupService::class);
        $mock->shouldReceive('restore')
            ->andThrow(new RestoreFailedException('Restore Operation Failed.', 'failed'));
   
        $result = $this->createBackupForRestoreTest();

        $dbConnection = $result['db_connection'];
        $backupJob = $result['backup_job'];

        $this->artisan('backup:restore',[
            'file' => $backupJob['relative_path'],
            '--id' => $dbConnection->id,
            ])
            ->expectsOutput("❌ Restore failed: Restore Operation Failed.")
            ->assertExitCode(1);//failure
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