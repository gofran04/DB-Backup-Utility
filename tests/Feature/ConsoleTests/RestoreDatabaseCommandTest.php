<?php

namespace Tests\Feature\ConsoleTests;

use Tests\TestCase;
use App\Models\DatabaseConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use App\Factories\DatabaseAdapterFactory;
use App\Services\Compression\CompressionServiceInterface;
use App\Services\DatabaseBackupService;

class RestoreDatabaseCommandTest extends TestCase
{
    use RefreshDatabase;
    
    protected string $storagePath;

    protected function setUp(): void
    {
        parent::setUp();

        // Set test-specific backup path
        $this->storagePath = storage_path('app/test-backups');

        // Create the directory if it doesn't exist
        if (!File::exists($this->storagePath)) {
            File::makeDirectory($this->storagePath, 0777, true, true);
        }
    }

    protected function tearDown(): void
    {
        // Clean up the test-backups folder
        if (File::exists($this->storagePath)) {
            File::cleanDirectory($this->storagePath);
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

    private function createBackupForRestoreTest(string $dbName = 'restore_db_test'): array
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