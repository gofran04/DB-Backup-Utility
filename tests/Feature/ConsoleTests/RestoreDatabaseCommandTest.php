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
        $db_connection = DatabaseConnection::factory()->create([
            'db_name'  => 'restore_db_test',  // that db will check by createDatabaseIfNotExists() and created if it deos not exist
        ]);

        $compressor = app(CompressionServiceInterface::class);
        $adapter = app(DatabaseAdapterFactory::class)->make($db_connection);
        $adapter->createDatabaseIfNotExists(); // check if db created by factory is really exist on the databasebase and create it if it does not exist

        $backupService = new DatabaseBackupService($adapter,$compressor);
        $backupJob = $backupService->backupUsingDbId($db_connection, $this->storagePath);
        $fullPath = storage_path('app/' . $backupJob['relative_path']);


        $this->artisan('backup:restore',[
            'file' => $backupJob['relative_path'],
            '--id' => $db_connection->id,
            ])
            ->expectsOutput("🔍 Loading DB config from database_connections table (ID: $db_connection->id)")
            ->expectsOutput("🚀 Starting restore...")
            ->expectsOutput("✅ Restore complete.")
            ->assertExitCode(0);

        $this->assertFileExists($fullPath);
    }
}