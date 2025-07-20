<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;
use App\Models\DatabaseConnection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Config;
use App\Services\DatabaseBackupService;
use App\Factories\DatabaseAdapterFactory;
use App\Services\Compression\CompressionServiceInterface;

class RestoreDBTest extends TestCase
{
    use DatabaseMigrations;

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

    public function test_retore_db_by_passing_db_id()
    {
        $db_connection = DatabaseConnection::factory()->create();

        $compressor = app(CompressionServiceInterface::class);
        $adapter = app(DatabaseAdapterFactory::class)->make($db_connection);

        $backupService = new DatabaseBackupService($adapter,$compressor);
        $backupJob = $backupService->backup($db_connection, $this->storagePath);

        $fullPath = storage_path('app/' . $backupJob['relative_path']);
        $data2 = [
            'db_id' => $db_connection->id,
            'file'  => $backupJob['relative_path']
        ];

        $response = $this->post('api/restore',$data2);
        $response->assertOk();
        $response->assertJson([
            'message' => 'Database restored successfully.',
        ]);
        $this->assertNotNull($fullPath, 'Backup path should not be null');
        $this->assertTrue(file_exists($fullPath), "Backup file does not exist: $fullPath");
    }

}