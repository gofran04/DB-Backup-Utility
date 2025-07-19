<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\DatabaseConnection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;

class BackupJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp():void
    {
        parent::setUp();
  
        $testBackupPath = storage_path('app/test-backups');
        File::ensureDirectoryExists($testBackupPath);
        Config::set('backup.storage_path', $testBackupPath);
    }

    public function test_store_new_backup_job()
    {
        $db_connection = DatabaseConnection::factory()->create();
       
        $data = ['db_id' => $db_connection->id];
        $response = $this->postJson('api/backup-jobs/',$data);

        $response->assertStatus(201);
        $this->assertDatabaseCount('backup_jobs', 1);

        $this->assertDirectoryExists(storage_path('app/test-backups'));
    
        // Assert the backup file exists
        $files = File::allFiles(storage_path('app/test-backups'));
        $this->assertNotEmpty($files);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('app/test-backups'));

        parent::tearDown();
    }

   
}