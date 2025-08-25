<?php

namespace Tests\Feature\APITests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\DatabaseConnection;
use App\Models\BackupJob;
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

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('app/test-backups'));

        parent::tearDown();
    }

    public function test_return_all_backup_jobs()
    {
        BackupJob::factory()->count(4)->create();
        $response = $this->getJson('api/backup-jobs');

        $response->assertOk();
        $response->assertJsonCount(4, 'data'); 
        $this->assertDatabaseCount('backup_jobs', 4);
    }

    public function test_return_specific_backup_job()
    {
        $backup_job = BackupJob::factory()->create();
        $response = $this->getJson('api/backup-jobs/'.$backup_job->id);

        $response->assertOk();
        $response->assertJson([
                'data' => [
                    'id' => $backup_job->id,
                ],
            ]);
        $this->assertDatabaseCount('backup_jobs', 1);
    }

    public function test_delete_specific_backup_job()
    {
        $backup_job = BackupJob::factory()->create();
        $response = $this->deleteJson('api/backup-jobs/'.$backup_job->id);

        $response->assertOk();
        $this->assertSoftDeleted($backup_job);
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

    public function test_prevent_store_backup_job_with_missing_inputs()
    {
        $response = $this->postJson('api/backup-jobs', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['db_id','db_profile']);
    }

    public function test_store_backup_job_with_invalid_db_id()
    {
        $response = $this->postJson('api/backup-jobs', ['db_id' => 999]);

        $response->assertStatus(422); 
    }   
}